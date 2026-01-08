<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;

class UserTypeFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Tipo de Usuario';
    }

    /**
     * The array of matched parameters.
     *
     * @return array|null
     */
    public function parameters(): ?array
    {
        return ['type'];
    }

    /**
     * Apply to a given Eloquent query builder.
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function run(Builder $builder): Builder
    {
        $type = $this->request->get('type');

        return $builder->when($type === 'superuser', function ($query) {
            $query->where('is_superuser', true);
        })->when($type === 'auditor', function ($query) {
            $query->where('is_superuser', false)
                  ->where('permissions->audit.can_audit', '1');
        })->when($type === 'regular', function ($query) {
            $query->where('is_superuser', false)
                  ->where(function ($query) {
                      $query->whereNull('permissions->audit.can_audit')
                            ->orWhere('permissions->audit.can_audit', '!=', '1');
                  });
        });
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        return [
            \Orchid\Screen\Fields\Select::make('type')
                ->options([
                    'superuser' => 'Super Usuario',
                    'auditor'   => 'Auditor',
                    'regular'   => 'Usuario Regular',
                ])
                ->empty('Todos')
                ->value($this->request->get('type'))
                ->title('Filtrar por Tipo'),
        ];
    }
}
