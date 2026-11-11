<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BaseApiController;
use Illuminate\Pagination\LengthAwarePaginator;

it('returns standardized api responses', function () {
    $controller = new class extends BaseApiController
    {
        public function success($data)
        {
            return $this->successResponse($data, 'ok', 201);
        }

        public function error($errors)
        {
            return $this->errorResponse($errors, 'bad', 400);
        }

        public function notFound()
        {
            return $this->notFoundResponse();
        }

        public function forbidden()
        {
            return $this->forbiddenResponse();
        }
    };

    $success = $controller->success(['a' => 1])->getData(true);
    $error = $controller->error(['field' => 'required'])->getData(true);
    $notFound = $controller->notFound()->getData(true);
    $forbidden = $controller->forbidden()->getData(true);

    expect($success['success'])->toBeTrue();
    expect($error['success'])->toBeFalse();
    expect($notFound['code'])->toBe(404);
    expect($forbidden['code'])->toBe(403);
});

it('returns paginated responses with metadata', function () {
    $controller = new class extends BaseApiController
    {
        public function paginate($paginator)
        {
            return $this->paginatedResponse($paginator, 'list');
        }
    };

    $items = collect([['id' => 1], ['id' => 2]]);
    $paginator = new LengthAwarePaginator($items, 2, 1, 1);

    $response = $controller->paginate($paginator)->getData(true);

    expect($response['data'])->toHaveCount(2);
    expect($response['pagination']['total'])->toBe(2);
});
