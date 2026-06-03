# arnipay for WooCommerce

Pasarela de pago para WooCommerce que integra **arnipay** (arnipay.com.py) en tiendas paraguayas. Permite a los clientes pagar con **QR**, **tigo money** y **Personal Pay** desde el checkout estándar de WooCommerce.

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-96588A?logo=woocommerce&logoColor=white)](https://woocommerce.com/)
[![License](https://img.shields.io/badge/license-GPL%20v2-blue)](LICENSE)

---

## Características

- Integración nativa con WooCommerce como método de pago.
- Soporte para los tres medios de pago de arnipay: QR, tigo money y Personal Pay.
- El cliente elige el método dentro del checkout — menos fricción.
- **Webhook firmado HMAC** con todas las protecciones de una pasarela seria:
  - Validación de firma con `hash_equals` (timing-safe).
  - Anti-replay con ventana de 900 segundos.
  - Validación de `X-Client-ID` contra el comercio configurado.
  - Idempotencia derivada del payload firmado.
  - Respuestas genéricas para prevenir enumeración de pedidos.
  - Validación estricta del monto reportado.
- Compatible con **HPOS** (High-Performance Order Storage de WooCommerce).
- Panel de configuración con estado visual y verificación end-to-end de credenciales y webhook.
- Suite de tests PHPUnit (61 tests, 96 aserciones).

---

## Requisitos

| Componente | Versión |
|---|---|
| WordPress | 6.0+ |
| WooCommerce | 9.0+ |
| PHP | 8.2+ |
| Moneda de la tienda | PYG (guaraní paraguayo) |
| HTTPS | Obligatorio (arnipay rechaza webhooks por HTTP) |

---

## Instalación

### Desde el ZIP

1. Descargá el ZIP de [la última release](../../releases).
2. En tu WordPress: `Plugins → Añadir nuevo → Subir plugin`.
3. Subí el ZIP, activá.
4. Configurá en `WooCommerce → Ajustes → Pagos → arnipay`.

### Desde el repositorio (para desarrollo)

```bash
cd wp-content/plugins/
git clone https://github.com/arnipay/arnipay-woo.git
```

---

## Configuración

1. **Conseguí tus credenciales** en arnipay (`arnipay.com.py`):
   - Client ID
   - Client Secret
   - Webhook Secret

2. **En el plugin**, completá los tres pasos del panel:
   - **Credenciales** → pegar Client ID + Client Secret → tocar "Verificar credenciales".
   - **Webhook** → copiar la URL que muestra el panel, configurarla en arnipay del lado del comercio, pegar el Webhook Secret en el plugin y **guardar cambios** antes de tocar "Verificar webhook".
   - **Activar** → marcar "Habilitar este método", elegir los medios de pago a mostrar, guardar.

3. **Probá** con un pedido pequeño en staging antes de operar en producción.

---

## Estructura del código

```
arnipay-woo/
├── arnipay-woo.php                              # Bootstrap del plugin
├── readme.txt                                   # Readme estándar de WordPress
├── includes/
│   ├── class-arnipay-woo-aw-plugin.php          # Carga clases, registra hooks
│   ├── class-arnipay-woo-aw.php                 # Fachada del SDK arnipay
│   ├── class-arnipay-woo-aw-gateway.php         # Gateway de WooCommerce
│   ├── admin/                                   # Definición de campos del admin
│   └── domain/                                  # Clases de dominio (extraídas en 1.0.27-1.0.28)
│       ├── class-arnipay-woo-aw-order-resolver.php
│       ├── class-arnipay-woo-aw-webhook-handler.php
│       ├── class-arnipay-woo-aw-checkout-view.php
│       ├── class-arnipay-woo-aw-admin-renderer.php
│       └── class-arnipay-woo-aw-verification-service.php
├── lib/
│   ├── PATCHES.md                               # Documenta los parches al SDK incluido
│   └── vendor/geekwalletsrl/arnipay-sdk/        # SDK de arnipay (parcheado)
├── assets/                                      # CSS, JS, íconos SVG
└── tests/                                       # Suite PHPUnit
```

---

## Tests

```bash
# Requisito: PHP 8.2+ y PHPUnit 9.x
phpunit --testsuite unit
```

Salida esperada: `OK (61 tests, 96 assertions)`.

Los tests usan stubs de WordPress y WooCommerce — corren en milisegundos sin necesitar un WordPress real. El bootstrap está en `tests/bootstrap.php`.

---

## Seguridad

Si encontrás una vulnerabilidad, **no abras un issue público**. Reportala en privado por email a quien mantenga el repo. Las áreas más sensibles del código están documentadas en `tests/unit/` — la suite cubre todas las capas de validación del webhook.

Ver también [`lib/PATCHES.md`](lib/PATCHES.md) para entender los parches aplicados al SDK incluido (timeouts cURL, validación de respuesta).

---

## Contribuciones

Si querés contribuir, abrí un issue describiendo el cambio propuesto antes de mandar un PR. Para cualquier cambio que toque la lógica del webhook, las clases de `includes/domain/` o la seguridad, **agregá tests** que cubran el caso.

Antes de enviar:

```bash
# Lint PHP
find . -name "*.php" -not -path "./lib/vendor/*" -not -path "./tests/*" \
  -exec php -l {} \; | grep -v "No syntax"

# Tests
phpunit --testsuite unit
```

---

## Licencia

[GPL v2 o posterior](LICENSE) — la licencia estándar de WordPress.

---

## Recursos

- [SDK de arnipay (PHP)](https://github.com/GEEKWALLETSRL/arnipay-sdk-php)
- [Documentación del API arnipay](https://github.com/GEEKWALLETSRL/arnipay-api)
- [Documentación de WooCommerce](https://woocommerce.com/documentation/)
