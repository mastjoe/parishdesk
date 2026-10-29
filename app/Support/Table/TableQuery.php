<?php

namespace App\Support\Table;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Applies the shared search, filter, sort and pagination rules used by every
 * admin list page, reading them from the query string.
 *
 * Query string: ?search=…&sort=name|-name&filter[status]=active&per_page=25
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
class TableQuery
{
    /** @var list<string> */
    private array $searchable = [];

    /** @var list<string> */
    private array $sortable = [];

    private string $defaultSort = '-created_at';

    /** @var array<string, Closure(Builder<TModel>, string): void> */
    private array $filters = [];

    /**
     * @param  Builder<TModel>  $query
     */
    private function __construct(
        private Builder $query,
        private Request $request,
    ) {}

    /**
     * Start a table query for the given builder.
     *
     * @template TQueryModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TQueryModel>  $query
     * @return self<TQueryModel>
     */
    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /**
     * Set the columns the search box looks in.
     *
     * @param  list<string>  $columns
     * @return $this
     */
    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    /**
     * Set the columns that may be sorted on, and the default sort.
     *
     * @param  list<string>  $columns
     * @return $this
     */
    public function sortable(array $columns, string $default = '-created_at'): self
    {
        $this->sortable = $columns;
        $this->defaultSort = $default;

        return $this;
    }

    /**
     * Register a named filter applied when present in the query string.
     *
     * @param  Closure(Builder<TModel>, string): void  $callback
     * @return $this
     */
    public function filter(string $name, Closure $callback): self
    {
        $this->filters[$name] = $callback;

        return $this;
    }

    /**
     * Apply everything and paginate the results.
     *
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $defaultPerPage = 15): LengthAwarePaginator
    {
        $search = $this->search();

        if ($search !== '' && $this->searchable !== []) {
            $this->query->where(function (Builder $query) use ($search): void {
                foreach ($this->searchable as $column) {
                    $query->orWhere($column, 'like', '%'.addcslashes($search, '%_\\').'%');
                }
            });
        }

        foreach ($this->activeFilters() as $name => $value) {
            ($this->filters[$name])($this->query, $value);
        }

        [$column, $direction] = $this->sort();
        $this->query->orderBy($column, $direction);

        $perPage = min(max((int) $this->request->integer('per_page', $defaultPerPage), 5), 100);

        return $this->query->paginate($perPage)->withQueryString();
    }

    /**
     * Get the current table state so the page can render its controls.
     *
     * @return array{search: string, sort: string, filters: array<string, string>}
     */
    public function state(): array
    {
        [$column, $direction] = $this->sort();

        return [
            'search' => $this->search(),
            'sort' => ($direction === 'desc' ? '-' : '').$column,
            'filters' => $this->activeFilters(),
        ];
    }

    /**
     * Get the trimmed search term.
     */
    private function search(): string
    {
        return trim((string) $this->request->string('search'));
    }

    /**
     * Get the validated sort column and direction.
     *
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function sort(): array
    {
        $sort = (string) $this->request->string('sort', $this->defaultSort);
        $column = ltrim($sort, '-');

        if (! in_array($column, $this->sortable, true)) {
            $sort = $this->defaultSort;
            $column = ltrim($sort, '-');
        }

        return [$column, str_starts_with($sort, '-') ? 'desc' : 'asc'];
    }

    /**
     * Get the registered filters present in the query string.
     *
     * @return array<string, string>
     */
    private function activeFilters(): array
    {
        $requested = (array) $this->request->input('filter', []);

        return collect($requested)
            ->only(array_keys($this->filters))
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->all();
    }
}
