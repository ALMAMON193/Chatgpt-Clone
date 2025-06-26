<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Device;
use App\Models\UsageLog;
use App\Traits\ApiResponse;
use Illuminate\Support\Str;
use App\Models\Conversation;
use Illuminate\Http\Request;
use App\Models\ConversationData;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class ConversationApiController extends Controller
{
    use ApiResponse;

    // Generate or validate visitor_id
    public function generateVisitorId(Request $request)
    {
        try {
            $visitorId = $request->header('X-Visitor-ID') ?? Str::random(32);
            Device::firstOrCreate(['device_id' => $visitorId]);
            return $this->sendResponse(['visitor_id' => $visitorId], 'Visitor ID generated or validated successfully');
        } catch (Exception $e) {
            Log::error('Failed to generate visitor ID: ' . $e->getMessage());
            return $this->sendError('Failed to generate visitor ID', ['error' => $e->getMessage()], 500);
        }
    }

    // Get conversations by user ID or visitor ID
    public function getConversationsByUserId()
    {
        $user = auth()->user();
        $visitorId = request()->header('X-Visitor-ID');
        if (!$user && !$visitorId) {
            Log::warning('Unauthorized access to conversations: No user or visitor ID provided');
            return $this->sendError('Unauthorized', ['error' => 'User not authenticated or Visitor ID missing'], 401);
        }

        try {
            $conversations = collect();

            if ($user) {
                Log::info("Fetching conversations for authenticated user ID: {$user->id}");
                $conversations = Conversation::where('user_id', $user->id)->get();
            } else {
                Log::info("Fetching conversations for guest with visitor ID: {$visitorId}");
                Device::firstOrCreate(['device_id' => $visitorId]);
                $conversations = Conversation::where('device_id', $visitorId)->get();
            }

            if ($conversations->isEmpty()) {
                Log::info("No conversations found for " . ($user ? "user ID {$user->id}" : "visitor ID {$visitorId}"));
            }

            $success = $conversations->map(function ($conversation) {
                return [
                    'conversation_id' => $conversation->id,
                    'conversation_name' => $conversation->name ?? 'Untitled Conversation',
                    'user_id' => $conversation->user_id,
                    'created_at' => $conversation->created_at->toISOString(),
                    'updated_at' => $conversation->updated_at->toISOString(),
                ];
            });

            return $this->sendResponse($success, 'Conversations retrieved successfully');
        } catch (Exception $e) {
            Log::error('Failed to retrieve conversations: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve conversations', ['error' => $e->getMessage()], 500);
        }
    }


    // Store conversation
    public function storeConversation(Request $request)
    {
        // Record start time
        $startTime = microtime(true);

        $user = auth()->user();
        $visitorId = $request->header('X-Visitor-ID');

        if (!$user && !$visitorId) {
            return $this->sendError('Unauthorized', ['error' => 'User not authenticated or Visitor ID missing'], 401);
        }

        if (!$user) {
            Device::firstOrCreate(['device_id' => $visitorId]);
        }

        // Check usage limit
        $usageCheck = $this->checkUsageLimit($user, $visitorId);
        if ($usageCheck['exceeded']) {
            return $this->sendError('Usage Limit Exceeded', ['error' => $usageCheck['message']], 429);
        }

        $apiKey = config('services.openAi.api_key');
        if (!$apiKey) {
            return $this->sendError('API Key Missing', ['error' => 'OpenAI API key is not configured'], 500);
        }

        // Validate request
        $validated = $request->validate([
            'input_text' => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
            'message_history' => 'nullable|array',
            'message_history.*.role' => 'required|string|in:user,assistant',
            'message_history.*.content' => 'required|string',
        ]);

        try {
            $inputText = $validated['input_text'];
            $conversationId = $validated['conversation_id'] ?? null;
            $messageHistory = $validated['message_history'] ?? [];
            $outputText = null;
            $messages = [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ];

            $conversation = null;

            // Handle existing conversation
            if ($conversationId) {
                $query = Conversation::where('id', $conversationId);
                if ($user) {
                    $query->where('user_id', $user->id);
                } else {
                    $query->where('device_id', $visitorId);
                }
                $conversation = $query->first();

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
            } elseif (!$user) {
                $messages = array_merge($messages, $messageHistory);
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
                $conversationName = substr($inputText, 0, 20);
                if (strlen($inputText) > 20) {
                    $conversationName .= '...';
                }
                $conversation = Conversation::create([
                    'user_id' => $user ? $user->id : null,
                    'device_id' => $user ? null : $visitorId,
                    'name' => $conversationName,
                ]);
            }

            // Store conversation data
            $conversationData = ConversationData::create([
                'conversation_id' => $conversation->id,
                'input_text' => $inputText,
                'output_text' => $outputText,
            ]);

            // Calculate duration
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2); // Duration in seconds

            // Log usage for non-subscribed users or guests
            if (!$user || ($user && !$user->is_subscribe)) {
                $this->logUsage($user, $visitorId, $duration);
            }

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

    // Get conversation by ID
    public function getConversationDetails($conversation_id)
    {
        $user = auth()->user();
        $visitorId = request()->header('X-Visitor-ID');

        if (!$user && !$visitorId) {
            return $this->sendError('Unauthorized', ['error' => 'User not authenticated or Visitor ID missing'], 401);
        }

        // Validate conversation_id
        if (!is_numeric($conversation_id) || $conversation_id <= 0) {
            return $this->sendError('Invalid Input', ['error' => 'Invalid conversation ID'], 422);
        }

        try {
            // Fetch the conversation with its conversation data
            $query = Conversation::where('id', $conversation_id)
                ->with(['conversationData' => function ($query) {
                    $query->orderBy('created_at', 'asc');
                }]);

            if ($user) {
                $query->where('user_id', $user->id);
            } else {
                $query->where('device_id', $visitorId);
            }

            $conversation = $query->first();

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

            return $this->sendResponse($success, 'Conversation details retrieved successfully');
        } catch (Exception $e) {
            Log::error('Failed to retrieve conversation details: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve conversation details', ['error' => $e->getMessage()], 500);
        }
    }


    // Check usage limit for guests (10 min) and non-subscribed users (30 min)
    private function checkUsageLimit($user, $visitorId)
    {
        if ($user && $user->is_subscribe) {
            return [
                'exceeded' => false,
                'message' => '',
                'used_seconds' => 0,
                'limit_seconds' => 0,
            ];
        }

        $today = now()->startOfDay();
        $limitSeconds = $user ? 2 * 60 : 1 * 60;

        if ($user) {
            $usageSeconds = UsageLog::where('user_id', $user->id)
                ->where('created_at', '>=', $today)
                ->sum('duration_seconds');
        } else {
            $usageSeconds = UsageLog::where('device_id', $visitorId)
                ->where('created_at', '>=', $today)
                ->sum('duration_seconds');
        }

        $exceeded = $usageSeconds >= $limitSeconds;
        $message = $exceeded ? ($user ? 'Daily 2-minute limit reached' : 'You are already 1 min over') : '';

        return [
            'exceeded' => $exceeded,
            'message' => $message,
            'used_seconds' => $usageSeconds,
            'limit_seconds' => $limitSeconds,
        ];
    }

    // Log usage for guests and non-subscribed users
    private function logUsage($user, $visitorId, $durationSeconds)
    {
        if ($user && $user->is_subscribe) {
            return;
        }

        try {
            UsageLog::create([
                'user_id' => $user ? $user->id : null,
                'device_id' => $user ? null : $visitorId,
                'duration_seconds' => $durationSeconds,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to log usage: ' . $e->getMessage());
        }
    }
}
