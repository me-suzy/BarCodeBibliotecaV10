#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de monitorizare continua pentru Aleph
Verifica statusul la fiecare 30 secunde si inregistreaza cand se opreste
"""

import time
import urllib.request
import urllib.error
from datetime import datetime
import os

SERVER_IP = "YOUR-IP-or-http://WEBSITE"
CATALOG_URL = f"http://{SERVER_IP}:8991/F"
CHECK_INTERVAL = 30  # secunde
LOG_FILE = "monitor_aleph.log"

def check_aleph():
    """Verifica daca Aleph este accesibil"""
    try:
        req = urllib.request.Request(CATALOG_URL)
        req.add_header('User-Agent', 'Mozilla/5.0')
        with urllib.request.urlopen(req, timeout=10) as response:
            return True, response.getcode(), ""
    except urllib.error.URLError as e:
        return False, 0, str(e)
    except Exception as e:
        return False, 0, str(e)

def log_event(message):
    """Inregistreaza un eveniment in log"""
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    log_entry = f"[{timestamp}] {message}\n"
    print(log_entry.strip())
    
    log_path = os.path.join(os.path.dirname(__file__), LOG_FILE)
    try:
        with open(log_path, 'a', encoding='utf-8') as f:
            f.write(log_entry)
    except:
        pass

def main():
    print("=" * 80)
    print("MONITORIZARE ALEPH - Verificare continua")
    print(f"Server: {CATALOG_URL}")
    print(f"Interval verificare: {CHECK_INTERVAL} secunde")
    print("Apasa Ctrl+C pentru a opri")
    print("=" * 80)
    print()
    
    log_event("Monitorizare pornita")
    
    consecutive_failures = 0
    last_status = None
    
    try:
        while True:
            is_up, status_code, error = check_aleph()
            current_time = datetime.now()
            hour = current_time.hour
            
            if is_up:
                if last_status == False:
                    # Aleph s-a repornit
                    log_event(f"ALEPH S-A REPORNIT! (dupa {consecutive_failures} esecuri consecutive)")
                consecutive_failures = 0
                last_status = True
                status_msg = f"OK - HTTP {status_code} (ora {hour}:{current_time.minute:02d})"
            else:
                consecutive_failures += 1
                if last_status == True:
                    # Aleph tocmai s-a oprit
                    log_event(f"ALEPH S-A OPRIT! Eroare: {error}")
                last_status = False
                status_msg = f"ESEC - {error} (ora {hour}:{current_time.minute:02d}) - {consecutive_failures} esecuri consecutive"
            
            print(f"[{current_time.strftime('%H:%M:%S')}] {status_msg}")
            
            if consecutive_failures >= 3:
                log_event(f"ATENTIE: {consecutive_failures} esecuri consecutive - Aleph pare sa fie oprit")
            
            time.sleep(CHECK_INTERVAL)
            
    except KeyboardInterrupt:
        log_event("Monitorizare oprita de utilizator")
        print("\nMonitorizare oprita.")

if __name__ == "__main__":
    main()

