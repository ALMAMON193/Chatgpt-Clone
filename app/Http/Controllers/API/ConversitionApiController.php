<?php

namespace App\Http\Controllers\API;

use Exception;
use App\Traits\ApiResponse;
use App\Models\Conversation;
use App\Models\Conversition;
use Illuminate\Http\Request;
use App\Models\ConversationData;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class ConversitionApiController extends Controller
{
    use ApiResponse;
    //get conversition by user id
    public function getConversationsByUserId()
    {
        $user = auth()->user();
        if (!$user) {
            return $this->sendError('Unauthorized', ['error' => 'User not authenticated'], 401);
        }

        try {
            $conversations = Conversation::where('user_id', $user->id)->get();

            $success = $conversations->map(function ($conversation) {
                return [
                    'conversation_id' => $conversation->id,
                    'conversation_name' => $conversation->name,
                    'user_id' => $conversation->user_id,
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,

                ];
            });

            return $this->sendResponse($success, 'Conversations retrieved successfully');
        } catch (Exception $e) {
            Log::error('Failed to retrieve conversations: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve conversations', ['error' => $e->getMessage()], 500);
        }
    }
    //store conversition

    public function storeConversation(Request $request)
    {
        // Check if user is authenticated
        $user = auth()->user();
        if (!$user) {
            return $this->sendError('Unauthorized', ['error' => 'User not authenticated'], 401);
        }

        $apiKey = config('services.openAi.api_key');
        if (!$apiKey) {
            return $this->sendError('API Key Missing', ['error' => 'OpenAI API key is not configured'], 500);
        }

        // Validate request
        $validated = $request->validate([
            'input_text' => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
        ]);

        try {
            $inputText = $validated['input_text'];
            $conversationId = $validated['conversation_id'] ?? null;
            $outputText = null;
            $messages = [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ];

            $conversation = null;

            // Handle existing conversation
            if ($conversationId) {
                $conversation = Conversation::where('id', $conversationId)
                    ->where('user_id', $user->id)
                    ->first();

                if ($conversation) {
                    // Fetch last 10 messages for context
                    $lastMessages = ConversationData::where('conversation_id', $conversationId)
                        ->orderBy('created_at', 'desc')
                        ->take(10)
                        ->get()
                        ->reverse()
                        ->values();

                    foreach ($lastMessages as $message) {
                        $messages[] = ['role' => 'user', 'content' => $message->input_text];
                        $messages[] = ['role' => 'assistant', 'content' => $message->output_text];
                    }
                }
            }

            // Add current input text
            $messages[] = ['role' => 'user', 'content' => $inputText];

            // Make API request to OpenAI
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => $messages,
                'max_tokens' => 300,
            ]);

            // Check if the API request was successful
            if ($response->successful()) {
                $responseData = $response->json();
                $outputText = $responseData['choices'][0]['message']['content'] ?? null;
            } else {
                throw new Exception('OpenAI API request failed: ' . $response->body());
            }

            // Create new conversation if none exists
            if (!$conversation) {
                // Generate conversation name based on input
                $conversationName = substr($inputText, 0, 20);
                if (strlen($inputText) > 20) {
                    $conversationName .= '...';
                }
                $conversation = Conversation::create([
                    'user_id' => $user->id,
                    'name' => $conversationName,
                ]);
            }

            // Store conversation data
            $conversationData = ConversationData::create([
                'conversation_id' => $conversation->id,
                'input_text' => $inputText,
                'output_text' => $outputText,
            ]);

            // Prepare success response
            $success = [
                'conversation_id' => $conversation->id,
                'conversation_name' => $conversation->name,
                'user_id' => $conversation->user_id,
                'input_text' => $conversationData->input_text,
                'output_text' => $conversationData->output_text,
                'created_at' => $conversationData->created_at,
                'updated_at' => $conversationData->updated_at,
            ];
            $message = 'Conversation updated successfully';
            return $this->sendResponse($success, $message);
        } catch (Exception $e) {
            Log::error('Conversation creation failed: ' . $e->getMessage());
            return $this->sendError('Failed to process conversation', ['error' => $e->getMessage()], 500);
        }
    }

    //get conversation by id
    public function getConversationDetails($conversation_id)
    {
        // Check if user is authenticated
        $user = auth()->user();
        if (!$user) {
            return $this->sendError('Unauthorized', ['error' => 'User not authenticated'], 401);
        }

        // Validate conversation_id
        if (!is_numeric($conversation_id) || $conversation_id <= 0) {
            return $this->sendError('Invalid Input', ['error' => 'Invalid conversation ID'], 422);
        }

        try {
            // Fetch the conversation with its conversation data
            $conversation = Conversation::where('id', $conversation_id)
                ->where('user_id', $user->id)
                ->with(['conversationData' => function ($query) {
                    $query->orderBy('created_at', 'asc');
                }])
                ->first();

            // Check if conversation exists and belongs to the user
            if (!$conversation) {
                return $this->sendError('Not Found', ['error' => 'Conversation not found or not owned by user'], 404);
            }

            // Prepare response data
            $success = [
                'id' => $conversation->id,
                'user_id' => $conversation->user_id,
                'name' => $conversation->name ?? 'Untitled Conversation',
                'created_at' => $conversation->created_at->toISOString(),
                'updated_at' => $conversation->updated_at->toISOString(),
                'conversation_data' => $conversation->conversationData->map(function ($data) {
                    return [
                        'id' => $data->id,
                        'input_text' => $data->input_text,
                        'output_text' => $data->output_text,
                        'created_at' => $data->created_at->toISOString(),
                        'updated_at' => $data->updated_at->toISOString(),
                    ];
                })->toArray(),
            ];

            $message = 'Conversation details retrieved successfully';
            return $this->sendResponse($success, $message);
        } catch (Exception $e) {
            Log::error('Failed to retrieve conversation details: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve conversation details', ['error' => $e->getMessage()], 500);
        }
    }
}
