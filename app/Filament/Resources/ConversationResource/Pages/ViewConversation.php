<?php

namespace App\Filament\Resources\ConversationResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\ConversationResource;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    public function getRecord(): \Illuminate\Database\Eloquent\Model
    {
        return $this->record->load('user', 'conversationData');
    }
}
