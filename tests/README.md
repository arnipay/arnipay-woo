# Tests automatizados

Suite de tests unitarios e integrados para las clases de dominio del plugin.
Cubren la lógica que más nos importa que no se rompa: resolución de pedidos,
comparación segura de identificadores, validación de monto, idempotencia de
webhooks, firma HMAC y anti-replay.

## Requisitos

- PHP 8.1+
- PHPUnit 9.x

## Cómo correrlos

Desde la raíz del plugin:

```
phpunit --testsuite unit
```

Sin instalación global, también funciona con un PHAR:

```
php phpunit.phar --testsuite unit
```

## Qué cubren

| Archivo | Qué prueba |
|---|---|
| `unit/Order_Resolver_Test.php` | `Arnipay_Woo_AW_Order_Resolver` aislado: identifier_equals, lookups por reference/link_id/payment_id, validación de formato de referencia, amount_matches, build_dedupe_key, remember_webhook con tope, store_event_identifiers. |
| `unit/Webhook_Pipeline_Test.php` | Pipeline real con el SDK incluido: firma válida acepta, firma manipulada rechaza, payload manipulado rechaza, payload incompleto rechaza, anti-replay del timestamp, idempotencia de retries. |

## Por qué stubs en lugar de un WordPress real

Las clases bajo prueba (`Arnipay_Woo_AW_Order_Resolver`, `Arnipay_Woo_AW_Webhook_Handler`)
están deliberadamente desacopladas del runtime de WordPress y WooCommerce. El
bootstrap (`tests/bootstrap.php`) stub-ea las funciones y la clase `WC_Order`
mínimas que el código bajo prueba toca. Esto da tests **rápidos y
deterministas** que se ejecutan en milisegundos y no dependen de una base de
datos. La contrapartida: lo que no toca el stub no se cubre desde acá — para
eso hay que probar end-to-end en un sitio de staging con un webhook real.

## Cuándo correrlos

- Antes de cada commit que toque archivos en `includes/domain/`.
- Antes de empaquetar una versión.
- Como red de seguridad al refactorizar otras partes del gateway.

Los tests detectaron un bug latente en la primera ejecución
(`_arnipay_processed_webhooks` arrancaba con una entrada vacía espuria por
el cast `(array)''` de WooCommerce). Esa clase de regresión es exactamente
para lo que esta suite existe.
