=== Agrocampo – Cotizador PDF ===
Contributors: agrocampo
Tags: pdf, cotizador, cotizacion
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv2 or later

Genera cotizaciones PDF con el formato Agrocampo desde un endpoint REST.

== Descripción ==

Este plugin añade un endpoint REST que entrega una cotización en PDF usando el formato de la plantilla Agrocampo.

Endpoint:

- URL: /wp-json/agrocampo-cotizador/v1/pdf
- Métodos: GET o POST
- Parámetro opcional: download=1 para descargar el PDF en vez de mostrarlo inline.

Puedes enviar datos en JSON. Si no envías nada, se usan valores de ejemplo basados en la plantilla.

== Ejemplo de uso ==

GET:
/wp-json/agrocampo-cotizador/v1/pdf?download=1

POST:
{
  "quote_number": "120126-4",
  "client": "CONSTRUCTORA PEHUENCHE LTDA",
  "items": [
    {
      "number": "1",
      "code": "1408502610101",
      "detail": "FILTRO DE MOTOR",
      "unit_price": 9880,
      "discount": "",
      "quantity": 1
    }
  ]
}

== Instalación ==

1. Sube el ZIP desde Plugins > Añadir nuevo > Subir plugin.
2. Activa el plugin.
3. Visita /wp-json/agrocampo-cotizador/v1/pdf

== Changelog ==

= 1.0.0 =
* Versión inicial con endpoint PDF.
