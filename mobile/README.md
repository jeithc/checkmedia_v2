# CheckMedia Auditor (mobile)

Expo + TypeScript app for field auditors. Online MVP (no offline queue yet — that's sub-proyecto 3).

## Requisitos
- Node 20+ y npm
- Un teléfono Android con **Expo Go** (Play Store), o un emulador Android (requiere Android Studio + Java)

## Configurar el API
Copia `.env.example` a `.env` y ajusta:

```
EXPO_PUBLIC_API_URL=https://v2.pptefectimedios.com
```

Para apuntar a un backend local corriendo en tu máquina, usa la IP LAN del equipo (no `localhost`, el teléfono no lo resuelve), p.ej. `http://192.168.1.50:8000`.

## Correr

```
cd mobile
npm install
npx expo start
```

Escanea el QR con Expo Go (Android).

## Pruebas

```
npm run typecheck
npm test
```

## Smoke manual (en el teléfono)
1. Login con un usuario auditor (campo **usuario**, no email).
2. Cierra y reabre la app -> debe ofrecer desbloqueo biométrico si hay sesión guardada.
3. Buscar un código de espacio válido -> ver datos + pauta; código inválido -> "no encontrado".
4. "Auditar" -> marcar criterios (Malo exige comentario), tomar >=1 foto.
5. Guardar sin foto -> error de validación.
6. Guardar con foto -> "Auditoría guardada"; reintentar el mismo espacio/semana -> mensaje de duplicado (409).
7. Verifica en el panel admin que la auditoría llegó con su foto y watermark con la hora de captura.
