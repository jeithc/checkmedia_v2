<?php

declare(strict_types=1);

namespace App\Orchid\Presenters;

use Illuminate\Support\Str;
use Laravel\Scout\Builder;
use Orchid\Screen\Contracts\Personable;
use Orchid\Screen\Contracts\Searchable;
use Orchid\Support\Presenter;

class UserPresenter extends Presenter implements Personable, Searchable
{
    /**
     * Returns the label for this presenter, which is used in the UI to identify it.
     */
    public function label(): string
    {
        return 'Users';
    }

    /**
     * Returns the title for this presenter, which is displayed in the UI as the main heading.
     */
    public function title(): string
    {
        return $this->entity->name;
    }

    /**
     * Returns the subtitle for this presenter, which provides additional context about the user.
     */
    public function subTitle(): string
    {
        if ($this->entity->is_superuser) {
            return 'Super Usuario';
        }

        if ($this->entity->hasAccess('audit.can_audit')) {
            return 'Auditor';
        }

        $roles = $this->entity->roles->pluck('name')->implode(' / ');

        return (string) Str::of($roles)
            ->limit(20)
            ->whenEmpty(fn () => 'Usuario regular');
    }

    /**
     * Returns the URL for this presenter, which is used to link to the user's edit page.
     */
    public function url(): string
    {
        return route('platform.systems.users.edit', $this->entity);
    }

    /**
     * Returns the URL for the user's avatar image, using avatar_path if exists, otherwise Gravatar.
     */
    public function image(): ?string
    {
        $path = $this->entity->avatar_path;

        if ($path) {
            // If it's already a full URL, check if we need to fix the localhost part
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                // If it contains localhost/storage, we might want to fix it to use current site URL
                // But generally, if it's a URL, Orchid's internal logic should have set it.
                // Re-pointing to asset('storage/...') is safer if we extract the path.
                if (str_contains($path, '/storage/')) {
                    $path = explode('/storage/', $path)[1];
                    return asset('storage/' . $path);
                }
                return $path;
            }

            return asset('storage/' . ltrim($path, '/'));
        }

        $hash = md5(strtolower(trim($this->entity->email)));

        // Use Gravatar's built-in 'identicon' as fallback (no external dependency)
        return "https://www.gravatar.com/avatar/$hash?d=identicon";
    }

    /**
     * Returns the number of models to return for a compact search result.
     * This method is used by the search functionality to display a list of matching results.
     */
    public function perSearchShow(): int
    {
        return 3;
    }

    /**
     * Returns a Laravel Scout builder object that can be used to search for matching users.
     * This method is used by the search functionality to retrieve a list of matching results.
     */
    public function searchQuery(?string $query = null): Builder
    {
        return $this->entity->search($query);
    }
}
