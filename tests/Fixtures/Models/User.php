<?php

namespace TobiasNagel\FluxBreadcrumbs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use TobiasNagel\FluxBreadcrumbs\Tests\Fixtures\Enums\Status;

class User extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'status' => Status::class,
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return 'Display: '.$this->name;
    }

    protected function initials(): Attribute
    {
        return Attribute::make(get: fn () => strtoupper(substr((string) $this->name, 0, 1)));
    }
}
