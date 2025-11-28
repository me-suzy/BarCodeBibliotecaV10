#!/bin/bash
# Script pentru a creste swap-ul pe serverul Linux
# ATENTIE: Ruleaza acest script pe serverul Linux, nu pe Windows!

echo "=========================================="
echo "Creste Swap pe Server Linux"
echo "=========================================="
echo ""

# Verifica swap actual
echo "[1] Swap actual:"
swapon --show
free -m | grep Swap
echo ""

# Verifica spatiu disk disponibil
echo "[2] Spatiu disk disponibil:"
df -h / | tail -1
echo ""

# Creeaza swap file nou (2 GB)
SWAP_FILE="/swapfile2"
SWAP_SIZE="2048"  # MB

echo "[3] Creeaza swap file nou (${SWAP_SIZE} MB)..."
if [ -f "$SWAP_FILE" ]; then
    echo "ATENTIE: $SWAP_FILE exista deja!"
    read -p "Vrei sa il suprascrii? (y/n): " answer
    if [ "$answer" != "y" ]; then
        echo "Anulat."
        exit 1
    fi
    swapoff $SWAP_FILE 2>/dev/null
    rm -f $SWAP_FILE
fi

dd if=/dev/zero of=$SWAP_FILE bs=1M count=$SWAP_SIZE
chmod 600 $SWAP_FILE
mkswap $SWAP_FILE
swapon $SWAP_FILE

echo ""
echo "[4] Swap nou activat:"
swapon --show
free -m | grep Swap
echo ""

# Adauga in /etc/fstab pentru permanent
echo "[5] Adauga in /etc/fstab pentru permanent..."
if ! grep -q "$SWAP_FILE" /etc/fstab; then
    echo "$SWAP_FILE none swap sw 0 0" >> /etc/fstab
    echo "Adaugat in /etc/fstab"
else
    echo "Deja exista in /etc/fstab"
fi

echo ""
echo "=========================================="
echo "GATA! Swap crescut cu succes!"
echo "=========================================="
echo ""
echo "Verifica:"
echo "  free -m"
echo "  swapon --show"

