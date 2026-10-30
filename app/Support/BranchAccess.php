<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BranchAccess
{
    public const BRANCHES = ['Bandung', 'JakSel', 'JakPus'];

    public static function selected(Request $request): ?string
    {
        $value = $request->input('branch', $request->header('X-Branch'));
        if ($value === '') {
            $value = null;
        }
        validator(['branch' => $value], ['branch' => ['nullable', Rule::in(self::BRANCHES)]])->validate();
        if (! $request->user()->canViewAllBranches()) {
            abort_if($value && $value !== $request->user()->branch, 403, 'Anda hanya dapat mengakses cabang sendiri.');

            return $request->user()->branch;
        }

        return $value;
    }

    public static function required(Request $request): string
    {
        $branch = self::selected($request);
        if (! $branch) {
            throw ValidationException::withMessages(['branch' => 'Pilih cabang Bandung, JakSel, atau JakPus terlebih dahulu.']);
        }

        return $branch;
    }

    public static function scope(Builder $query, Request $request): Builder
    {
        $branch = self::selected($request);
        if (! $request->user()->canViewAllBranches() && ! $branch) {
            return $query->whereRaw('1=0');
        }

        return $branch ? $query->where($query->getModel()->qualifyColumn('branch'), $branch) : $query;
    }

    public static function authorize(Request $request, Model $record): void
    {
        if ($request->user()->canViewAllBranches()) {
            return;
        }
        abort_unless($request->user()->branch && $record->branch === $request->user()->branch, 404);
        self::selected($request);
    }
}
