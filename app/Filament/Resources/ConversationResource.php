<?php

namespace App\Filament\Resources;

use Filament\Tables\Table;
use App\Models\Conversation;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use App\Filament\Resources\ConversationResource\Pages;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Conversation Details')
                    ->schema([
                        TextEntry::make('name')->label('Conversation Name'),
                        TextEntry::make('user.name')->label('User'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Conversation Data')
                    ->schema([
                        RepeatableEntry::make('conversationData')
                            ->schema([
                                TextEntry::make('input_text')->label('Input Text'),
                                TextEntry::make('output_text')->label('Output Text'),
                                TextEntry::make('created_at')->dateTime(),
                            ])
                            ->columns(2)
                            ->label('Messages'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Conversation Name')->sortable()->searchable(),
                TextColumn::make('user.name')->label('User')->sortable()->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                // Add filters here if needed
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->actions([
                \Filament\Tables\Actions\ViewAction::make(),
                \Filament\Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
            'view' => Pages\ViewConversation::route('/{record}'),
        ];
    }
}
