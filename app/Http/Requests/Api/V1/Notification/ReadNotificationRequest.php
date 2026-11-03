<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Notification;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /notifications/read: one id, or none for "mark all as read".
 */
class ReadNotificationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['id' => ['nullable', 'string', 'uuid']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['id' => 'notifikasi'];
    }

    public function notificationId(): ?string
    {
        $id = $this->validated('id');

        return is_string($id) ? $id : null;
    }
}
