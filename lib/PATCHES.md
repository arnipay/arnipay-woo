# Parches aplicados al SDK incluido

El SDK de arnipay incluido en `lib/vendor/geekwalletsrl/arnipay-sdk/` tiene
modificaciones específicas para este plugin. **Si actualizás el SDK por
Composer, los cambios se perderán** — esta nota documenta qué reaplicar.

## Archivos modificados

### `src/Gateway/Client.php`

Tres cambios en el método que ejecuta la petición cURL (cerca de la línea 108
en el SDK original):

**1. Timeout de conexión** — evita que un backend lento cuelgue el checkout.

```php
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($curl, CURLOPT_TIMEOUT, 30);
```

Ubicación: después de la sección donde se aplican los demás `curl_setopt`,
antes del `curl_exec($curl)`.

**2. Validación de respuesta vacía** — evita pasar `false` a `json_decode()`
cuando la API responde sin cuerpo.

```php
if (!is_string($response) || $response === '') {
    throw new GatewayException('Empty API response', $statusCode ?: 0);
}
```

Ubicación: después de `$response = curl_exec($curl);` y antes del
`json_decode()`.

**3. Validación de JSON decodificable** — evita errores aguas abajo si la API
devuelve algo que no es JSON válido.

```php
if (!is_array($responseData)) {
    throw new GatewayException('Invalid JSON response from API', $statusCode ?: 0);
}
```

Ubicación: después de `$responseData = json_decode($response, true);`.

## Cómo reaplicar tras actualizar el SDK

1. Actualizar el SDK con Composer normalmente.
2. Abrir el `Client.php` del SDK nuevo y reaplicar los tres bloques de arriba.
3. Volver a poner el bloque de advertencia al inicio del archivo (ver el
   `Client.php` actual como referencia).
4. Probar el flujo de pago end-to-end en staging antes de subir a producción.

## Por qué los cambios viven en el SDK y no en el plugin

El `Client` del SDK no expone hooks para configurar timeouts ni para
interceptar la respuesta antes del `json_decode`. Crear una subclase
requeriría duplicar el método HTTP entero (~80 líneas), lo cual aumenta la
deuda técnica en vez de reducirla. Para dos cambios chicos y bien
documentados, el parche directo con marca visible es la opción más simple y
mantenible.

Si en algún momento Geek Wallet libera una versión del SDK con timeouts
configurables, este archivo se puede eliminar y los cambios se vuelven
innecesarios.
