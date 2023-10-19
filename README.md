# OKOVISION

Interface web de supervison d'une chaudiere Okofen

<http://okovision.dronek.com>

Update: I did make a Fork to update a function that would not work in PHP8 on Raspberry Pi. Making the cron not getting back the proper info from the boiler.

How to install on Linux: Tester on Raspberry Pi 0 W with Raspbian Lite 12

sudo apt-get -y install mariadb-server

sudo mysql -e "CREATE USER 'okouser'@'localhost' IDENTIFIED BY 'okopass';"
sudo mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'okouser'@'localhost' ;"

sudo apt-get -y install apache2
sudo systemctl enable apache2

sudo apt update
sudo apt-get -y install php php-cli php-common
sudo apt-get -y install php-mysql php-mbstring php-xml php-curl php-gd php-mbstring php-intl php-zip

cd /var/www/
sudo wget https://github.com/stawen/okovision/archive/master.zip
sudo unzip master.zip
sudo mv okovision-master/ okovision/
sudo rm master.zip
sudo chown www-data:www-data -R okovision/

sudo cp /var/www/okovision/install/099-okovision.conf /etc/apache2/sites-available/.
sudo a2ensite 099-okovision.conf
sudo a2dissite 000-default
sudo service apache2 reload
