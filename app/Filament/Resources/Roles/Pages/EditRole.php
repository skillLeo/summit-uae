<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Support\Audit;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

/** @property Role $record */
class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    public function getTitle(): string
    {
        return \App\Filament\Resources\Users\RoleLabel::of($this->record->name);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data + ['matrix' => RoleResource::matrixOf($this->record)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Role $record */
        $before = $record->permissions->pluck('name')->sort()->values()->all();
        if (isset($data['name'])) {
            $record->forceFill(['name' => $data['name']])->save();
        }
        $after = RoleResource::permissionsFrom((array) ($data['matrix'] ?? []));
        $record->syncPermissions($after);
        sort($after);
        Audit::log('roles', 'update', $record, [
            'role' => $record->name,
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
        ]);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(fn (): bool => RoleResource::canDelete($this->record))->authorize(fn (): bool => RoleResource::canDelete($this->record))];
    }
}
