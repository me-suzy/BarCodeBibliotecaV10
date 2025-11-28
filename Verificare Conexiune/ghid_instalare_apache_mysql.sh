#!/bin/bash
# Script pentru instalare Apache + MySQL cu configurare optima pentru server cu resurse limitate

echo "=========================================="
echo "Instalare Apache + MySQL pentru Biblioteca"
echo "Configurare optima pentru resurse limitate"
echo "=========================================="
echo ""

# Verifica daca sunt deja instalate
if rpm -qa | grep -q httpd; then
    echo "Apache este deja instalat!"
    read -p "Vrei sa continui? (y/n): " confirm
    if [ "$confirm" != "y" ]; then
        exit 1
    fi
fi

if rpm -qa | grep -q mysql; then
    echo "MySQL este deja instalat!"
    read -p "Vrei sa continui? (y/n): " confirm
    if [ "$confirm" != "y" ]; then
        exit 1
    fi
fi

# 1. Instaleaza Apache
echo "[1] Instaleaza Apache..."
yum install -y httpd php php-mysql

# 2. Configureaza Apache pentru resurse limitate
echo ""
echo "[2] Configureaza Apache pentru resurse limitate..."
APACHE_CONF="/etc/httpd/conf/httpd.conf"

# Backup configurare
cp $APACHE_CONF ${APACHE_CONF}.backup

# Limiteaza procesele Apache
sed -i 's/^MaxClients.*/MaxClients 20/' $APACHE_CONF
sed -i 's/^ServerLimit.*/ServerLimit 20/' $APACHE_CONF

# Schimba DocumentRoot (daca vrei)
read -p "Vrei sa schimbi DocumentRoot in /hosting/www? (y/n): " change_docroot
if [ "$change_docroot" = "y" ]; then
    mkdir -p /hosting/www
    sed -i 's|^DocumentRoot.*|DocumentRoot "/hosting/www"|' $APACHE_CONF
    sed -i 's|<Directory "/var/www/html">|<Directory "/hosting/www">|' $APACHE_CONF
fi

# 3. Instaleaza MySQL/MariaDB
echo ""
echo "[3] Instaleaza MySQL/MariaDB..."
yum install -y mysql-server mysql

# 4. Configureaza MySQL pentru resurse limitate
echo ""
echo "[4] Configureaza MySQL pentru resurse limitate..."
MY_CNF="/etc/my.cnf"

# Backup configurare
cp $MY_CNF ${MY_CNF}.backup

# Adauga configurari pentru resurse limitate
cat >> $MY_CNF << 'EOF'

# Configurari pentru resurse limitate
[mysqld]
innodb_buffer_pool_size = 256M
max_connections = 50
key_buffer_size = 64M
tmp_table_size = 32M
max_heap_table_size = 32M
query_cache_size = 32M
query_cache_limit = 2M
thread_cache_size = 8
table_open_cache = 256
EOF

# 5. Porneste servicii
echo ""
echo "[5] Porneste servicii..."
service mysqld start
chkconfig mysqld on

service httpd start
chkconfig httpd on

# 6. Configureaza MySQL root password
echo ""
echo "[6] Configureaza MySQL root password..."
read -p "Introdu parola pentru MySQL root (sau Enter pentru a sari): " mysql_pass
if [ -n "$mysql_pass" ]; then
    mysqladmin -u root password "$mysql_pass"
    echo "Parola MySQL root setata!"
else
    echo "Parola MySQL root nu a fost setata. Ruleaza manual:"
    echo "  mysqladmin -u root password 'parola_ta'"
fi

# 7. Creeaza baza de date pentru biblioteca
echo ""
echo "[7] Creeaza baza de date pentru biblioteca..."
read -p "Vrei sa creezi baza de date 'biblioteca'? (y/n): " create_db
if [ "$create_db" = "y" ]; then
    if [ -n "$mysql_pass" ]; then
        mysql -u root -p"$mysql_pass" -e "CREATE DATABASE IF NOT EXISTS biblioteca;"
    else
        mysql -u root -e "CREATE DATABASE IF NOT EXISTS biblioteca;"
    fi
    echo "Baza de date 'biblioteca' creata!"
fi

# 8. Verifica status
echo ""
echo "[8] Verifica status servicii..."
service httpd status
service mysqld status

# 9. Verifica resurse
echo ""
echo "[9] Verifica resurse..."
free -m
echo ""
ps aux | grep -E 'httpd|mysqld' | grep -v grep | wc -l
echo "procese Apache + MySQL"

echo ""
echo "=========================================="
echo "INSTALARE COMPLETA!"
echo "=========================================="
echo ""
echo "Apache:"
echo "  Status: service httpd status"
echo "  DocumentRoot: $(grep '^DocumentRoot' $APACHE_CONF | awk '{print $2}')"
echo ""
echo "MySQL:"
echo "  Status: service mysqld status"
echo "  Conectare: mysql -u root -p"
echo ""
echo "Resurse:"
echo "  Verifica: free -m"
echo "  Monitorizeaza: python monitor_auto_verificare.py"
echo ""
echo "Urmatorii pasi:"
echo "1. Copiaza fisierele PHP in DocumentRoot"
echo "2. Importa baza de date (daca este necesar)"
echo "3. Configureaza virtual hosts (daca este necesar)"
echo "4. Configureaza firewall pentru portul 80/443"

