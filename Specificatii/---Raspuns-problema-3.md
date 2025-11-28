## Ce am aflat
- `start_stop` chiar rulează `aleph_shutdown`, care execută oprire completă: termină `jobd`, `server_monitor -ks`, creează fișiere `util_e_*_stop`, și la final încearcă să oprească `JBOSS` (`jboss_shutdown.sh`).
- Logul `aleph_shutdown.log` arată că la fiecare oprire apare excepția JBOSS (`Connection refused` la `127.0.0.1:2892`). Asta înseamnă că scriptul de shutdown se declanșează în realitate, nu doar simulat.
- `apachectl` și `aleph_shutdown` sunt scripturile standard Ex Libris; dacă se rulează `start_stop stop`, ele se execută exact în ordinea văzută.

## Pași următori sugerați
- Identifică “cine” lansează comanda `aleph_shutdown` sau `start_stop stop`. La 17:00 poate un cron, un script personalizat ori un job Aleph.
- Verifică în `aleph_shutdown.log` timestamp-urile când apar mesajele “shutdown jobd…” (momentul opririi). Dacă au loc zilnic la 17:00, corelează cu alte loguri (cron, `messages`, `secure`).
- Caută în cron/at/alte scripturi referințe la `aleph_shutdown`, `start_stop stop`, `server_monitor -ks` sau fișiere `util_e_.._stop`.

### Cum să continuăm
Pot să-ți pregătesc un script care caută pe server toate aparițiile `aleph_shutdown`, `start_stop stop`, `server_monitor -ks` și fișiere `util_e_*_stop`. Așa vedem dacă există vreun cron sau script personalizat ce face oprirea. Spune-mi dacă vrei să-l generezi.