FROM golang:1.23-alpine AS builder

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-w -s" -o /worker ./cmd/worker

FROM alpine:3.20

RUN apk add --no-cache ffmpeg ca-certificates tzdata

WORKDIR /app
COPY --from=builder /worker /app/worker

ENV TEMP_DIR=/tmp/media

RUN mkdir -p /tmp/media && adduser -D -u 1000 appuser && chown -R appuser:appuser /app /tmp/media
USER appuser

ENTRYPOINT ["/app/worker"]
