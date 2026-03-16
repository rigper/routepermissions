Route Permissions Pro para IssabelPBX 2.12.0.3 Debian 12
=====================================

Objetivo
--------
Controlar permisos de rutas salientes por extensión, con:
- reglas explícitas por extensión/ruta
- default por ruta
- destino alterno al denegar
- redirección por prefijo
- operación masiva desde GUI

Notas técnicas
--------------
1) El hook principal se inserta en:
   - macro-dialout-trunk
   - 
2) En cada contexto outrt-N el módulo inyecta:
   - __ROUTEPERM_ROUTE_ID
   - __ROUTENAME

3) El AGI busca reglas en este orden:
   - extensión + route_id
   - default de ruta (exten=-1)
   - meta global (exten=-1, route_id=-1)

4) Si la ruta es denegada:
   - con prefix: reinyección a from-internal con prefijo + número
   - con faildest: Goto directo al destino
   - sin nada: Playback(ss-noservice) + Hangup

Instalación sugerida
--------------------
1. Copiar el directorio routepermissions a:
   /var/www/html/admin/modules/routepermissions

2. Revisar permisos:
   chown -R asterisk:asterisk /var/www/html/admin/modules/routepermissions
   chmod -R 755 /var/www/html/admin/modules/routepermissions

3. Instalar desde Module Admin de Issabel / FreePBX legacy.

4. Verificar que el AGI haya quedado en:
   /var/lib/asterisk/agi-bin/checkperms.agi

   Si no se copió automáticamente:
   cp /var/www/html/admin/modules/routepermissions/agi-bin/checkperms.agi /var/lib/asterisk/agi-bin/checkperms.agi
   chown asterisk:asterisk /var/lib/asterisk/agi-bin/checkperms.agi
   chmod 755 /var/lib/asterisk/agi-bin/checkperms.agi

5. Apply Config.

Recomendaciones
---------------
- Probar primero con una sola ruta y una sola extensión.
- Confirmar en Asterisk CLI:
  dialplan show outrt-<ID>
  dialplan show macro-dialout-trunk

- Para depuración:
  tail -f /var/log/asterisk/full | grep routepermissions

Limitaciones conocidas
----------------------
- La correcta inserción de splice depende del framework legacy de Issabel/FreePBX.
- Algunos entornos antiguos pueden requerir ajustar los nombres de tablas si su esquema difiere.
- Si el destino drawselects no está disponible en la GUI, el módulo cae a un input de texto para faildest.
