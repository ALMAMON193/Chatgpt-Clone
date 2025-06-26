<?php

namespace App\Filament\Resources\PlanResource\Pages;

use Filament\Actions;
use App\Filament\Resources\PlanResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPlan extends ViewRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
