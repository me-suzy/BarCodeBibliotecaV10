#!/bin/bash
# Script pentru a opri procesul telnet zombie care consuma 99.9% CPU

echo "=========================================="
echo "Oprire proces telnet zombie (PID 1682)"
echo "=========================================="
echo ""

# Verifica daca procesul exista
if ps -p 1682 > /dev/null 2>&1; then
    echo "Proces gasit:"
    ps aux | grep 1682 | grep -v grep
    echo ""
    echo "Opreste procesul..."
    kill -9 1682
    sleep 2
    
    # Verifica daca s-a oprit
    if ps -p 1682 > /dev/null 2>&1; then
        echo "EROARE: Procesul nu s-a oprit!"
    else
        echo "SUCCES: Procesul a fost oprit!"
    fi
else
    echo "Procesul nu exista (poate a fost deja oprit)"
fi

echo ""
echo "Verifica toate procesele telnet:"
ps aux | grep telnet | grep -v grep

echo ""
echo "Top procese dupa CPU (dupa oprirea telnet):"
ps aux --sort=-%cpu | head -11

