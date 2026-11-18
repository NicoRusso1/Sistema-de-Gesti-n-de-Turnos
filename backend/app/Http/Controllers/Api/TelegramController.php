<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * Vinculación de la cuenta del paciente con el bot de Telegram, usado para
 * recibir los recordatorios de turno (los manda n8n).
 */
class TelegramController extends Controller
{
    #[OA\Get(
        path: '/api/me/telegram',
        summary: 'Estado de la vinculación con Telegram del usuario autenticado',
        tags: ['Telegram'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado de la vinculación.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'linked', type: 'boolean', example: false),
                    new OA\Property(property: 'available', type: 'boolean', example: true),
                ])
            ),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'linked'    => filled($request->user()->telegram_chat_id),
            'available' => $this->botUsername() !== null,
        ]);
    }

    #[OA\Post(
        path: '/api/me/telegram/link',
        summary: 'Generar el link para vincular la cuenta con el bot de Telegram',
        tags: ['Telegram'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Link de vinculación (t.me/<bot>?start=<token>).',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'url', type: 'string', example: 'https://t.me/SigtTurnosBot?start=abc123'),
                ])
            ),
            new OA\Response(response: 503, description: 'El bot de Telegram no está configurado.'),
        ]
    )]
    public function link(Request $request): JsonResponse
    {
        $bot = $this->botUsername();

        if (!$bot) {
            return response()->json(['message' => 'Las notificaciones por Telegram no están disponibles.'], 503);
        }

        $token = Str::random(40);

        $user = $request->user();
        $user->telegram_link_token = $token;
        $user->save();

        return response()->json(['url' => "https://t.me/{$bot}?start={$token}"]);
    }

    #[OA\Delete(
        path: '/api/me/telegram',
        summary: 'Desvincular Telegram',
        tags: ['Telegram'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Telegram desvinculado.')]
    )]
    public function unlink(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->telegram_chat_id = null;
        $user->telegram_link_token = null;
        $user->save();

        return response()->json(['message' => 'Telegram desvinculado. Ya no vas a recibir recordatorios por ese medio.']);
    }

    #[OA\Post(
        path: '/api/integrations/n8n/telegram/link',
        summary: 'Confirma la vinculación (lo llama n8n cuando el paciente envía /start <token> al bot)',
        tags: ['Telegram'],
        parameters: [
            new OA\Parameter(name: 'X-N8N-Secret', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['token', 'chat_id'],
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'chat_id', type: 'string', example: '123456789'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Cuenta vinculada.'),
            new OA\Response(response: 401, description: 'Secreto de n8n inválido.'),
            new OA\Response(response: 404, description: 'Token inválido o ya usado.'),
        ]
    )]
    public function confirmLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'   => ['required', 'string', 'max:64'],
            'chat_id' => ['required', 'regex:/^-?\d{1,20}$/'],
        ]);

        $patient = Patient::where('telegram_link_token', $data['token'])->first();

        if (!$patient) {
            return response()->json(['message' => 'El link de vinculación no es válido o ya fue usado.'], 404);
        }

        $chatId = (string) $data['chat_id'];

        // Si ese chat ya estaba vinculado a otra cuenta, se pasa a la nueva
        Patient::where('telegram_chat_id', $chatId)
            ->where('id', '!=', $patient->id)
            ->update(['telegram_chat_id' => null]);

        $patient->telegram_chat_id = $chatId;
        $patient->telegram_link_token = null;
        $patient->save();

        return response()->json([
            'message'    => 'Cuenta vinculada',
            'first_name' => $patient->first_name,
        ]);
    }

    private function botUsername(): ?string
    {
        $bot = ltrim((string) config('services.n8n.telegram_bot_username'), '@');

        return $bot !== '' ? $bot : null;
    }
}
