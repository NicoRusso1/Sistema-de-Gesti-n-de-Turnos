<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Role;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:patients,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'email.unique' => 'El correo electrónico ya se encuentra registrado',
        ]);

        $role = Role::where('name', RoleName::OperationalUser->value)->firstOrFail();
        $patientType = UserType::where('name', UserTypeName::Patient->value)->firstOrFail();

        $patient = Patient::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => strtolower($request->email),
            'password_hash' => Hash::make($request->password),
            'role_id' => $role->id,
            'user_type_id' => $patientType->id,
            'status' => 1,
        ]);

        return response()->json([
            'message' => 'Usuario registrado correctamente',
            'patient' => $patient
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'El campo correo electrónico es obligatorio.',
            'email.email' => 'El campo correo electrónico debe ser una dirección de correo válida.',
            'password.required' => 'El campo contraseña es obligatorio.',
        ]);

        $patient = Patient::where('email', strtolower($request->email))->first();

        if (! $patient || ! Hash::check($request->password, $patient->password_hash)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

                if ($patient->status !== 1) {
            return response()->json([
                'message' => 'La cuenta se encuentra dada de baja'
            ], 403);
        }

        $token = $patient->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $patient->load('role', 'userType'),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('role', 'userType'));
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresá un correo electrónico válido.',
        ]);
        $patient = Patient::active()->where('email', strtolower($request->email))->first();
        if (! $patient) {
            return response()->json([
                'message' => 'No encontramos ningún usuario registrado con ese correo electrónico.'
            ], 404);
        }
        // 1. Generar token firmado (estructura JWT temporal: base64(payload) + firma HMAC-SHA256)
        $payload = base64_encode(json_encode([
            'email' => $patient->email,
            'exp' => now()->addMinutes(60)->timestamp, // Expira en 60 minutos
        ]));
        $signature = hash_hmac('sha256', $payload, config('app.key'));
        $token = $payload . '.' . $signature;
        // 2. Guardar en la tabla password_reset_tokens para invalidar tras su uso
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $patient->email],
            [
                'token' => $token,
                'created_at' => now(),
            ]
        );
        $resetUrl = "http://localhost:4200/reset-password?token=" . urlencode($token);

        $htmlContent = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f3f4f6; color: #333333;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 15px;">
                <tr>
                    <td align="center">
                        <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); padding: 32px; border: 1px solid #e5e7eb;">
                            <tr>
                                <td style="text-align: center; padding-bottom: 20px;">
                                    <h2 style="color: #111827; margin: 0; font-size: 22px;">Recuperación de Contraseña</h2>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 15px; line-height: 1.6; color: #4b5563; padding-bottom: 24px;">
                                    <p style="margin: 0 0 12px 0;">Hola <strong>' . htmlspecialchars($patient->first_name) . '</strong>,</p>
                                    <p style="margin: 0 0 12px 0;">Recibimos una solicitud para restablecer la contraseña de tu cuenta. Hacé clic en el siguiente botón para continuar:</p>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding-bottom: 28px;">
                                    <!-- Botón interactivo -->
                                    <a href="' . $resetUrl . '" target="_blank" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: bold; font-size: 15px; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);">
                                        Click aquí para recuperar contraseña
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 13px; line-height: 1.5; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 20px;">
                                    <p style="margin: 0 0 8px 0;">Este enlace es válido por <strong>60 minutos</strong>.</p>
                                    <p style="margin: 0 0 16px 0;">Si no solicitaste este cambio, podés ignorar este correo de forma segura.</p>
                                    <p style="margin: 0; font-size: 12px; color: #9ca3af;">Si el botón no abre, copiá y pegá el siguiente enlace en tu navegador:<br>
                                        <a href="' . $resetUrl . '" style="color: #2563eb; word-break: break-all;">' . $resetUrl . '</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';

        Mail::html($htmlContent, function ($message) use ($patient) {
            $message->to($patient->email)
                    ->subject('Recuperación de contraseña');
        });
        return response()->json([
            'message' => 'Te hemos enviado un correo con el enlace para restablecer tu contraseña.'
        ]);
    }
    /**
     * Procesar el cambio de contraseña
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'token.required' => 'El token es obligatorio.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);
        // 1. Validar estructura del token firmado
        $parts = explode('.', $request->token);
        if (count($parts) !== 2) {
            return response()->json(['message' => 'El formato del token es inválido.'], 400);
        }
        [$payloadBase64, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $payloadBase64, config('app.key'));
        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'El token no es válido o ha sido alterado.'], 400);
        }
        $payload = json_decode(base64_decode($payloadBase64), true);
        if (! $payload || ! isset($payload['email'], $payload['exp'])) {
            return response()->json(['message' => 'Información del token corrupta o inválida.'], 400);
        }
        // 2. Verificar expiración
        if (now()->timestamp > $payload['exp']) {
            return response()->json(['message' => 'El enlace ha expirado. Por favor solicitá uno nuevo.'], 400);
        }
        // 3. Verificar que el token exista en BD (evita reutilización)
        $resetRecord = DB::table('password_reset_tokens')->where('email', $payload['email'])->first();
        if (! $resetRecord || $resetRecord->token !== $request->token) {
            return response()->json(['message' => 'El token ya ha sido utilizado o es inválido.'], 400);
        }
        // 4. Buscar usuario y actualizar password_hash
        $patient = Patient::where('email', $payload['email'])->first();
        if (! $patient) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }
        $patient->password_hash = Hash::make($request->password);
        $patient->save();
        // 5. Eliminar el token usado
        DB::table('password_reset_tokens')->where('email', $payload['email'])->delete();
        return response()->json([
            'message' => 'Tu contraseña ha sido restablecida correctamente. Ya podés iniciar sesión.'
        ]);
    }
}
