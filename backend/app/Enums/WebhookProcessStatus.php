<?php

namespace App\Enums;

enum WebhookProcessStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Masuk, menunggu proses',
            self::Processed => 'Diproses',
            self::Ignored => 'Diabaikan',
            self::Failed => 'Gagal diproses',
            self::Rejected => 'Ditolak (tanda tangan)',
        };
    }
}
