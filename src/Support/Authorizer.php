<?php

namespace AmjadIqbal\FilamentRecordPdf\Support;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Policy check for one record. The first ability that the record's policy defines decides,
 * so a policy can add downloadPdf() to differ from view(). With no policy, or a policy that
 * defines none of the abilities, access to the panel is the gate.
 */
class Authorizer
{
    /**
     * @param  array<string>  $abilities
     */
    public static function allows(Model $record, array $abilities, ?Authenticatable $user = null): bool
    {
        $user ??= static::user();
        $policy = Gate::getPolicyFor($record);

        if ($policy === null) {
            return true;
        }

        foreach ($abilities as $ability) {
            if (method_exists($policy, $ability)) {
                return Gate::forUser($user)->allows($ability, $record);
            }
        }

        return true;
    }

    public static function user(): ?Authenticatable
    {
        return Filament::auth()->user() ?? auth()->user();
    }

    public static function guardName(): ?string
    {
        return Filament::getCurrentOrDefaultPanel()?->getAuthGuard() ?? config('auth.defaults.guard');
    }
}
