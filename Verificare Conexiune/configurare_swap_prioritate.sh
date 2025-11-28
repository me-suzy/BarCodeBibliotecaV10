#!/bin/bash
# Script pentru configurare prioritate swap si activare swap-ul nou

echo "=========================================="
echo "Configurare Prioritate Swap"
echo "=========================================="
echo ""

# Verifica swap-urile actuale
echo "[1] Swap-uri actuale:"
swapon -s
echo ""

# Opreste swap-ul vechi temporar
echo "[2] Opreste swap-ul vechi temporar..."
swapoff /dev/sda2

# Activeaza swap-ul nou cu prioritate mai mare
echo "[3] Activeaza swap-ul nou cu prioritate mai mare..."
swapon -p 10 /swapfile2

# Reactiveaza swap-ul vechi cu prioritate mai mica
echo "[4] Reactiveaza swap-ul vechi cu prioritate mai mica..."
swapon -p 5 /dev/sda2

# Verifica swap-urile
echo ""
echo "[5] Swap-uri dupa configurare:"
swapon -s
echo ""

# Verifica memorie
echo "[6] Memorie disponibila:"
free -m
echo ""

# Actualizeaza /etc/fstab pentru prioritati
echo "[7] Actualizeaza /etc/fstab pentru prioritati..."
# Backup
cp /etc/fstab /etc/fstab.backup

# Actualizeaza linia pentru swap-ul nou (prioritate mai mare)
sed -i 's|^/swapfile2.*|/swapfile2 none swap sw,pri=10 0 0|' /etc/fstab

# Actualizeaza linia pentru swap-ul vechi (prioritate mai mica)
sed -i 's|^LABEL=SWAP-sda2.*|LABEL=SWAP-sda2 swap swap defaults,pri=5 0 0|' /etc/fstab

echo "Actualizat /etc/fstab"
echo ""

echo "=========================================="
echo "GATA! Swap-ul nou are acum prioritate mai mare!"
echo "=========================================="
echo ""
echo "Swap-ul nou (4 GB) va fi folosit PRIMUL"
echo "Swap-ul vechi (4 GB) va fi folosit DOAR daca cel nou se epuizeaza"
echo ""
echo "Verifica:"
echo "  swapon -s"
echo "  free -m"

