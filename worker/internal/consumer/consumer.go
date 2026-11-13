package consumer

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"log/slog"
	"sync"
	"time"

	"github.com/rabbitmq/amqp091-go"
	"wiz-media-worker/internal/config"
	"wiz-media-worker/internal/pipeline"
)

// PipelineRunner defines the interface for executing a media processing job.
type PipelineRunner interface {
	Process(ctx context.Context, job pipeline.MediaJob) error
}

// Message abstracts an incoming message delivery for decoupling and testing.
type Message interface {
	Body() []byte
	Ack() error
	Nack(requeue bool) error
}

type deliveryWrapper struct {
	d amqp091.Delivery
}

func (w *deliveryWrapper) Body() []byte {
	return w.d.Body
}

func (w *deliveryWrapper) Ack() error {
	return w.d.Ack(false)
}

func (w *deliveryWrapper) Nack(requeue bool) error {
	return w.d.Nack(false, requeue)
}

// ConsumerOption defines a functional option for Consumer.
type ConsumerOption func(*Consumer)

// WithReconnectDelay sets the delay before reconnecting on connection loss.
func WithReconnectDelay(delay time.Duration) ConsumerOption {
	return func(c *Consumer) {
		if delay > 0 {
			c.reconnectDelay = delay
		}
	}
}

// Consumer subscribes to the RabbitMQ queue, processes incoming media jobs, and acknowledges them.
type Consumer struct {
	cfg            *config.Config
	pipeline       PipelineRunner
	logger         *slog.Logger
	reconnectDelay time.Duration
}

// NewConsumer initializes a new Consumer instance.
func NewConsumer(cfg *config.Config, p PipelineRunner, logger *slog.Logger, opts ...ConsumerOption) *Consumer {
	if logger == nil {
		logger = slog.Default()
	}
	concurrency := cfg.Concurrency
	if concurrency <= 0 {
		concurrency = 2
	}
	cfg.Concurrency = concurrency

	c := &Consumer{
		cfg:            cfg,
		pipeline:       p,
		logger:         logger,
		reconnectDelay: 3 * time.Second,
	}

	for _, opt := range opts {
		opt(c)
	}

	return c
}

// Start begins consuming messages from RabbitMQ with automatic reconnect logic.
// It blocks until the context is canceled.
func (c *Consumer) Start(ctx context.Context) error {
	c.logger.Info("starting RabbitMQ consumer",
		"queue", c.cfg.RabbitMQQueue,
		"concurrency", c.cfg.Concurrency,
	)

	for {
		select {
		case <-ctx.Done():
			return ctx.Err()
		default:
		}

		err := c.consume(ctx)
		if err == nil {
			return nil
		}

		if errors.Is(err, context.Canceled) || errors.Is(err, context.DeadlineExceeded) {
			c.logger.Info("consumer context canceled, shutting down")
			return err
		}

		c.logger.Warn("RabbitMQ connection lost or failed to start, reconnecting...",
			"error", err,
			"reconnect_delay", c.reconnectDelay,
		)

		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-time.After(c.reconnectDelay):
		}
	}
}

func (c *Consumer) consume(ctx context.Context) error {
	conn, err := amqp091.Dial(c.cfg.RabbitMQURL)
	if err != nil {
		return fmt.Errorf("failed to dial rabbitmq: %w", err)
	}
	defer conn.Close()

	ch, err := conn.Channel()
	if err != nil {
		return fmt.Errorf("failed to open channel: %w", err)
	}
	defer ch.Close()

	// Set QoS prefetch count matching Concurrency
	err = ch.Qos(c.cfg.Concurrency, 0, false)
	if err != nil {
		return fmt.Errorf("failed to set channel QoS: %w", err)
	}

	closeChan := make(chan *amqp091.Error, 1)
	conn.NotifyClose(closeChan)

	deliveries, err := ch.Consume(
		c.cfg.RabbitMQQueue,
		"",    // consumer tag (auto-generated)
		false, // auto-ack = false
		false, // exclusive
		false, // no-local
		false, // no-wait
		nil,   // args
	)
	if err != nil {
		return fmt.Errorf("failed to start consuming from queue %s: %w", c.cfg.RabbitMQQueue, err)
	}

	c.logger.Info("connected to RabbitMQ and waiting for media jobs", "queue", c.cfg.RabbitMQQueue)

	sem := make(chan struct{}, c.cfg.Concurrency)
	var wg sync.WaitGroup

	for {
		select {
		case <-ctx.Done():
			c.logger.Info("graceful shutdown initiated, waiting for in-flight tasks...")
			_ = ch.Cancel("", false)
			wg.Wait()
			return ctx.Err()

		case amqpErr, ok := <-closeChan:
			if !ok || amqpErr == nil {
				wg.Wait()
				return errors.New("rabbitmq connection closed")
			}
			wg.Wait()
			return fmt.Errorf("rabbitmq connection error: %w", amqpErr)

		case d, ok := <-deliveries:
			if !ok {
				c.logger.Warn("delivery channel closed, waiting for in-flight tasks...")
				wg.Wait()
				return errors.New("deliveries channel closed")
			}

			sem <- struct{}{}
			wg.Add(1)

			go func(del amqp091.Delivery) {
				defer func() {
					<-sem
					wg.Done()
				}()

				msg := &deliveryWrapper{d: del}
				_ = c.HandleMessage(ctx, msg)
			}(d)
		}
	}
}

// HandleMessage parses the message payload, validates it, invokes the pipeline,
// and handles ACK / NACK routing (NACK without requeue routes directly to DLQ).
func (c *Consumer) HandleMessage(ctx context.Context, msg Message) error {
	body := msg.Body()

	var job pipeline.MediaJob
	if err := json.Unmarshal(body, &job); err != nil {
		c.logger.ErrorContext(ctx, "malformed message JSON, discarding to DLQ",
			"error", err,
			"body", string(body),
		)
		_ = msg.Nack(false) // requeue = false -> routed to DLQ
		return fmt.Errorf("malformed JSON: %w", err)
	}

	if err := job.Validate(); err != nil {
		c.logger.ErrorContext(ctx, "invalid media job schema, discarding to DLQ",
			"error", err,
			"job_id", job.JobID,
		)
		_ = msg.Nack(false) // requeue = false -> routed to DLQ
		return fmt.Errorf("invalid schema: %w", err)
	}

	c.logger.InfoContext(ctx, "dispatching job to pipeline",
		"job_id", job.JobID,
		"video_id", job.VideoID,
	)

	// Dispatch to pipeline
	if err := c.pipeline.Process(ctx, job); err != nil {
		c.logger.ErrorContext(ctx, "pipeline processing reported failure",
			"job_id", job.JobID,
			"error", err,
		)
	}

	// ACK message upon pipeline completion
	if err := msg.Ack(); err != nil {
		c.logger.ErrorContext(ctx, "failed to ack message",
			"job_id", job.JobID,
			"error", err,
		)
		return fmt.Errorf("failed to ack message: %w", err)
	}

	c.logger.InfoContext(ctx, "message acknowledged", "job_id", job.JobID)
	return nil
}
