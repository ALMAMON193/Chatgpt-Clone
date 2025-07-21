<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationData;
use App\Models\ConversationUsage;
use App\Models\User;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConversationApiController extends Controller
{
    use ApiResponse;

    public function createGuestUser(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'guest_token' => 'required|string',
        ]);
        $guestToken = $request->input('guest_token');
        $user       = User::where('guest_token', $guestToken)->first();
        if (! $user) {
            $user = User::create([
                'is_guest'    => true,
                'guest_token' => $guestToken,
            ]);
        }
        $responseData = [
            'guest_token' => $user->guest_token,
            'is_guest'    => $user->is_guest,
        ];
        $message = 'Guest user found or created';

        return $this->sendResponse($responseData, $message);
    }
    public function getConversations(Request $request): \Illuminate\Http\JsonResponse
    {
        $user       = auth('api')->user();
        $guestToken = $request->header('Guest-Token');
        if (! $user && ! $guestToken) {
            return $this->sendError('Unauthorized', ['error' => 'No user or guest token provided'], 401);
        }
        try {
            $query = Conversation::query();

            if ($user) {
                // Conversations by user_id
                $query->where('user_id', $user->id);
            }

            if ($guestToken) {
                // Also include guest token conversations if user not authenticated or include both
                $query->orWhere('guest_token', $guestToken);
            }

            $conversations = $query->get();

            $result = $conversations->map(function ($conversation) {
                return [
                    'conversation_id'   => $conversation->id,
                    'conversation_name' => $conversation->name,
                    'user_id'           => $conversation->user_id,
                    'guest_token'       => $conversation->guest_token,
                    'created_at'        => $conversation->created_at,
                    'updated_at'        => $conversation->updated_at,
                ];
            });

            return $this->sendResponse($result, 'Conversations retrieved successfully');
        } catch (Exception $e) {
            Log::error('Failed to retrieve conversations: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve conversations', ['error' => $e->getMessage()], 500);
        }
    }

    // Store conversation
    // public function storeConversation(Request $request)
    // {
    //     // Check if user is authenticated
    //     $user = auth('api')->user();

    //     if (! $user) {
    //         return $this->sendError('Unauthorized', ['error' => 'User not authenticated'], 401);
    //     }
    //     $apiKey = config('services.openAi.api_key');
    //     if (! $apiKey) {
    //         return $this->sendError('API Key Missing', ['error' => 'OpenAI API key is not configured'], 500);
    //     }

    //     // Validate request
    //     $validated = $request->validate([
    //         'input_text'      => 'required|string|max:2000',
    //         'conversation_id' => 'nullable|integer|exists:conversations,id',
    //     ]);

    //     try {
    //         $inputText      = $validated['input_text'];
    //         $conversationId = $validated['conversation_id'] ?? null;
    //         $outputText     = "response text";
    //         $messages       = [
    //             ['role' => 'system', 'content' => 'You are a helpful assistant.'],
    //         ];

    //         $conversation = null;

    //         // Handle existing conversation
    //         if ($conversationId) {
    //             $conversation = Conversation::where('id', $conversationId)
    //                 ->where('user_id', $user->id)
    //                 ->first();

    //             if ($conversation) {
    //                 // Fetch last 10 messages for context
    //                 $lastMessages = ConversationData::where('conversation_id', $conversationId)
    //                     ->orderBy('created_at', 'desc')
    //                     ->take(10)
    //                     ->get()
    //                     ->reverse()
    //                     ->values();

    //                 foreach ($lastMessages as $message) {
    //                     $messages[] = ['role' => 'user', 'content' => $message->input_text];
    //                     $messages[] = ['role' => 'assistant', 'content' => $message->output_text];
    //                 }
    //             }
    //         }

    //         // Add current input text
    //         $messages[] = ['role' => 'user', 'content' => $inputText];

    //         // Make API request to OpenAI
    //         $response = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $apiKey,
    //             'Content-Type'  => 'application/json',
    //         ])->post('https://api.openai.com/v1/chat/completions', [
    //             'model'      => 'gpt-3.5-turbo',
    //             'messages'   => $messages,
    //             'max_tokens' => 300,
    //         ]);

    //         // Check if the API request was successful
    //         if ($response->successful()) {
    //             $responseData = $response->json();
    //             $outputText   = $responseData['choices'][0]['message']['content'] ?? null;
    //         } else {
    //             throw new Exception('OpenAI API request failed: ' . $response->body());
    //         }

    //         // Create new conversation if none exists
    //         if (! $conversation) {
    //             // Generate conversation name based on input
    //             $conversationName = substr($inputText, 0, 20);
    //             if (strlen($inputText) > 20) {
    //                 $conversationName .= '...';
    //             }
    //             $conversation = Conversation::create([
    //                 'user_id' => $user->id,
    //                 'name'    => $conversationName,
    //             ]);
    //         }

    //         // Store conversation data
    //         $conversationData = ConversationData::create([
    //             'conversation_id' => $conversation->id,
    //             'input_text'      => $inputText,
    //             'output_text'     => $outputText,
    //         ]);

    //         // Prepare success response
    //         $success = [
    //             'conversation_id'   => $conversation->id,
    //             'conversation_name' => $conversation->name,
    //             'user_id'           => $conversation->user_id,
    //             'input_text'        => $conversationData->input_text,
    //             'output_text'       => $conversationData->output_text,
    //             'created_at'        => $conversationData->created_at,
    //             'updated_at'        => $conversationData->updated_at,
    //         ];
    //         $message = 'Conversation updated successfully';
    //         return $this->sendResponse($success, $message);
    //     } catch (Exception $e) {
    //         Log::error('Conversation creation failed: ' . $e->getMessage());
    //         return $this->sendError('Failed to process conversation', ['error' => $e->getMessage()], 500);
    //     }
    // }

    public function storeConversation(Request $request): \Illuminate\Http\JsonResponse
    {
        $apiKey = config('services.openAi.api_key');
        if (! $apiKey) {
            return $this->sendError('API Key Missing', ['error' => 'OpenAI API key is not configured'], 500);
        }

        $validated = $request->validate([
            'input_text'      => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
        ]);

        try {
            $user       = auth('api')->user();
            $guestToken = $request->header('Guest-Token');

            if (! $user && ! $guestToken) {
                return $this->sendError('Unauthorized', ['error' => 'No user or guest token provided'], 401);
            }

            // Handle guest user creation
            if (! $user && $guestToken) {
                $user = User::firstOrCreate(
                    ['guest_token' => $guestToken],
                    [
                        'is_guest'    => true,
                        'guest_token' => $guestToken,
                    ]
                );
            }

            $isGuest      = $user->is_guest;
            $isSubscribed = $user->is_subscribe ?? false;
            $now          = now();
            $today        = $now->toDateString();

            // Usage tracking
            $usage = ConversationUsage::firstOrNew([
                'user_id'     => $user->id,
                'guest_token' => $user->guest_token,
                'date'        => $today,
            ]);

            if (! $usage->first_used_at) {
                $usage->first_used_at = $now;
            }
            $usage->last_used_at = $now;

            $usedMinutes          = Carbon::parse($usage->first_used_at)->diffInMinutes($usage->last_used_at);
            $usage->usage_minutes = $usedMinutes;

            $maxMinutes = $isSubscribed ? 9999999 : ($isGuest ? 10 : 20);

            if ($usedMinutes >= $maxMinutes) {
                $errorMessage = $isGuest
                ? "You have exceeded the guest usage limit of $maxMinutes minutes."
                : "You have exceeded the subscription usage limit of $maxMinutes minutes.";

                return $this->sendError('Usage limit exceeded', ['error' => $errorMessage], 429);
            }

            $inputText      = $validated['input_text'];
            $conversationId = $validated['conversation_id'] ?? null;
            $outputText     = "response text"; // Replace with actual API call
            $messages       = [['role' => 'system', 'content' => 'You are a helpful assistant.']];
            $conversation   = null;

            if ($conversationId) {
                $query = Conversation::where('id', $conversationId);
                if ($user) {
                    $query->where('user_id', $user->id);
                } elseif ($guestToken) {
                    $query->where('guest_token', $guestToken);
                }
                $conversation = $query->first();

                if ($conversation) {
                    $lastMessages = ConversationData::where('conversation_id', $conversationId)
                        ->latest()->take(10)->get()->reverse();

                    foreach ($lastMessages as $msg) {
                        $messages[] = ['role' => 'user', 'content' => $msg->input_text];
                        $messages[] = ['role' => 'assistant', 'content' => $msg->output_text];
                    }
                }
            }

            $messages[] = ['role' => 'user', 'content' => $inputText];

            if (! $conversation) {
                $conversation = Conversation::create([
                    'user_id'     => $user->id ?? null,
                    'guest_token' => $guestToken ?? null,
                    'name'        => Str::limit($inputText, 20),
                    'started_at'  => now(),
                ]);
            }

            $conversationData = ConversationData::create([
                'conversation_id' => $conversation->id,
                'input_text'      => $inputText,
                'output_text'     => $outputText,
            ]);

            $usage->is_guest = $isGuest;
            $usage->save();

            return $this->sendResponse([
                'conversation_id'   => $conversation->id,
                'conversation_name' => $conversation->name,
                'user_id'           => $conversation->user_id,
                'guest_token'       => $conversation->guest_token,
                'input_text'        => $conversationData->input_text,
                'output_text'       => $conversationData->output_text,
                'usage_minutes'     => $usedMinutes,
                'limit_minutes'     => $maxMinutes,
            ], 'Conversation stored successfully');
        } catch (Exception $e) {
            Log::error('Conversation store error: ' . $e->getMessage());
            return $this->sendError('Failed to process conversation', ['error' => $e->getMessage()], 500);
        }
    }
    public function getConversationDetails(Request $request, $conversation_id): \Illuminate\Http\JsonResponse
    {
        $user       = auth('api')->user();
        $guestToken = $request->header('Guest-Token');

        if (! $user && ! $guestToken) {
            return $this->sendError('Unauthorized', ['error' => 'No user or guest token provided'], 401);
        }

        if (! is_numeric($conversation_id) || $conversation_id <= 0) {
            return $this->sendError('Invalid Input', ['error' => 'Invalid conversation ID'], 422);
        }

        try {
            $query = Conversation::where('id', $conversation_id);

            if ($user) {
                $query->where('user_id', $user->id);
            } elseif ($guestToken) {
                $query->where('guest_token', $guestToken);
            }

            $conversation = $query->with(['conversationData' => function ($q) {
                $q->orderBy('created_at', 'asc');
            }])->first();

            if (! $conversation) {
                return $this->sendError('Not Found', ['error' => 'Conversation not found or access denied'], 404);
            }

            $response = [
                'id'                => $conversation->id,
                'user_id'           => $conversation->user_id,
                'guest_token'       => $conversation->guest_token,
                'name'              => $conversation->name ?? 'Untitled Conversation',
                'created_at'        => $conversation->created_at->toISOString(),
                'updated_at'        => $conversation->updated_at->toISOString(),
                'conversation_data' => $conversation->conversationData->map(function ($data) {
                    return [
                        'id'          => $data->id,
                        'input_text'  => $data->input_text,
                        'output_text' => $data->output_text,
                        'created_at'  => $data->created_at->toISOString(),
                        'updated_at'  => $data->updated_at->toISOString(),
                    ];
                })->toArray(),
            ];

            return $this->sendResponse($response, 'Conversation details retrieved successfully');
        } catch (Exception $e) {
            Log::error('Failed to retrieve conversation details: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve conversation details', ['error' => $e->getMessage()], 500);
        }
    }
}
