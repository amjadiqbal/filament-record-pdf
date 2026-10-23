<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentRecordPdf\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $number
 * @property string $customer
 * @property ?string $email
 * @property string $total
 * @property ?string $pdf_path
 */
class Invoice extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    protected $hidden = ['secret_note'];

    protected $casts = ['paid' => 'boolean'];
}
