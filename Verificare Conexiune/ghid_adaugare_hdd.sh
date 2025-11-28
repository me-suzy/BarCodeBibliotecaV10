#!/bin/bash
# Script pentru adaugare HDD nou pentru hosting web
# ATENTIE: Ruleaza acest script dupa ce ai instalat fizic HDD-ul in server!

echo "=========================================="
echo "Adaugare HDD nou pentru hosting web"
echo "=========================================="
echo ""

# Verifica daca exista HDD nou
echo "[1] Verifica discuri disponibile..."
fdisk -l | grep -E "^Disk /dev"
echo ""

# Detecteaza HDD nou (sdc, sdd, etc.)
echo "[2] Detecteaza HDD nou..."
NEW_DISK=""
for disk in /dev/sdc /dev/sdd /dev/sde /dev/sdf; do
    if [ -b "$disk" ]; then
        if ! grep -q "$disk" /proc/partitions; then
            NEW_DISK="$disk"
            echo "HDD nou detectat: $NEW_DISK"
            break
        fi
    fi
done

if [ -z "$NEW_DISK" ]; then
    echo "EROARE: Nu s-a detectat HDD nou!"
    echo "Verifica ca HDD-ul este instalat fizic si conectat."
    exit 1
fi

echo ""
read -p "Vrei sa continui cu $NEW_DISK? (y/n): " confirm
if [ "$confirm" != "y" ]; then
    echo "Anulat."
    exit 1
fi

# Creeaza partiție
echo ""
echo "[3] Creeaza partiție nouă..."
echo "n
p
1


w
" | fdisk $NEW_DISK

sleep 2

# Formateaza partiția
echo ""
echo "[4] Formateaza partiția..."
PARTITION="${NEW_DISK}1"
mkfs.ext4 $PARTITION

# Creeaza director mount
echo ""
echo "[5] Creeaza director mount..."
mkdir -p /hosting

# Montează partiția
echo ""
echo "[6] Montează partiția..."
mount $PARTITION /hosting

# Adauga in /etc/fstab
echo ""
echo "[7] Adauga in /etc/fstab pentru permanent..."
if ! grep -q "$PARTITION" /etc/fstab; then
    echo "$PARTITION /hosting ext4 defaults 0 2" >> /etc/fstab
    echo "Adaugat in /etc/fstab"
else
    echo "Deja exista in /etc/fstab"
fi

# Verifica
echo ""
echo "[8] Verifica montarea..."
df -h /hosting
echo ""

echo "=========================================="
echo "GATA! HDD-ul a fost adaugat cu succes!"
echo "=========================================="
echo ""
echo "HDD montat in: /hosting"
echo "Spatiu disponibil:"
df -h /hosting
echo ""
echo "Urmatorii pasi:"
echo "1. Creeaza directoare pentru site-uri: mkdir -p /hosting/www"
echo "2. Instaleaza server web: yum install httpd"
echo "3. Configureaza DocumentRoot in /etc/httpd/conf/httpd.conf"
echo "4. Porneste serverul: service httpd start"

