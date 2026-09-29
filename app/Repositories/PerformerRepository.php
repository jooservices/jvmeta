<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Performer;
use Illuminate\Database\Eloquent\Builder;
use JOOservices\LaravelRepository\Contracts\RepositoryInterface;
use JOOservices\LaravelRepository\Repositories\EloquentRepository;
use JOOservices\LaravelRepository\Traits\HasCrud;
use JOOservices\LaravelRepository\Traits\HasFilter;
use JOOservices\LaravelRepository\Traits\HasOrder;
use JOOservices\LaravelRepository\Traits\HasRead;
use JOOservices\LaravelRepository\Traits\HasRequestQuery;

final class PerformerRepository extends EloquentRepository implements RepositoryInterface
{
    use HasCrud;
    use HasFilter;
    use HasOrder;
    use HasRead;
    use HasRequestQuery;

    public function __construct(Performer $model)
    {
        parent::__construct($model);
    }

    /**
     * Restrict the active query to performers whose romaji/kanji/kana name or
     * any alias matches the term (AC-5.3).
     */
    public function search(string $term): static
    {
        $pattern = '%' . $term . '%';

        $this->getQuery()->where(static function (Builder $query) use ($pattern): void {
            $query->where('name_romaji', 'like', $pattern)
                ->orWhere('name_kanji', 'like', $pattern)
                ->orWhere('name_kana', 'like', $pattern)
                ->orWhereHas('aliases', static function (Builder $aliases) use ($pattern): void {
                    $aliases->where('alias', 'like', $pattern);
                });
        });

        return $this;
    }

    /**
     * Eager-load aliases for performer list/summary responses (avoids N+1).
     * Detail loads linked_title_count separately via findForDisplay().
     */
    public function withDisplayRelations(): static
    {
        $this->getQuery()->with('aliases');

        return $this;
    }

    public function findForDisplay(int $id): ?Performer
    {
        $performer = $this->newQuery()->with('aliases')->withCount('movies')->find($id);

        return $performer instanceof Performer ? $performer : null;
    }
}
