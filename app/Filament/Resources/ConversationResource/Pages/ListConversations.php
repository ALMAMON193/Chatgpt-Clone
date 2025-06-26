<?php

namespace App\Filament\Resources\ConversationResource\Pages;

use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ConversationResource;

class ListConversations extends ListRecords
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
