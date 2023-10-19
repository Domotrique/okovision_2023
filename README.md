# OKOVISION

This project is a fork from [Stawen's Okovision](https://github.com/stawen/okovision) project which is no longer updated.

<http://okovision.dronek.com>

I wanted to update a function that would not work in PHP8 on Raspberry Pi. Making the cron not getting back the proper info from the boiler.
I ended up also fixing other bugs, and will maybe add more in the future.*

This project is made for Okofen firmware V1 with HTTP connection.

I will try explain you how to connect and have the tool ready to run with my setup, an **OKOFEN Pellematic Compact**.

![okofen](https://github.com/Domotrique/okovision_2023/assets/148430940/0b602cf1-83f3-4a7b-b27e-791ff7c21e08)

## INSTALL STEPS ON LINUX

```
sudo apt-get -y install mariadb-server
sudo mysql -e "CREATE USER 'okouser'@'localhost' IDENTIFIED BY 'okopass';"
sudo mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'okouser'@'localhost' ;"
sudo apt-get -y install apache2
sudo systemctl enable apache2

sudo apt update
sudo apt-get -y install php8.2 php8.2-cli php8.2-common
sudo apt-get -y install php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-gd php8.2-mbstring php8.2-intl php8.2-zip

cd /var/www/
sudo wget https://github.com/domotrique/okovision_2023/archive/master.zip
sudo unzip master.zip
sudo mv okovision-master/ okovision/
sudo rm master.zip
sudo chown www-data:www-data -R okovision/
sudo cp /var/www/okovision/install/099-okovision.conf /etc/apache2/sites-available/.
sudo a2ensite 099-okovision.conf
sudo a2dissite 000-default
sudo service apache2 reload
```
```sudo crontab -e```

Select NANO if asked and add the following line at the end of the file.

```22 */2 * * * cd /var/www/okovision; /usr/bin/php -f cron.php```

This last command will retrieve data from the okofen every 2 hours, 22 minutes past the hour (this is 10 min after the CSV is generated on the Okofen) and update the welcome page.
You can then hit **CTRL+X** then **Y** and then **ENTER** key.

## OKOFEN BOILER SETUP

You obviously need to have your boiler connected to your network (mine is connected via the network card in the door).
<img src="https://user-images.githubusercontent.com/148430940/276640137-60296231-e49c-4efd-8bd3-1d302871470c.jpg" width="400">

Then enable check what is your boiler IP in the Main Menu --> Generalities --> IP Config. My IP is 192.168.1.97.

*N.B. I strongly advise that you assign a static IP for your boiler in your network router setup.*

You can also check your login and password for the Okofen app if you need to pilot your system from outside home by going down in the IP Config menu.

<img src="https://user-images.githubusercontent.com/148430940/276642949-db8c907f-f5f6-452b-96af-23df09ab5c51.jpg" width="400">

You can double check that you have the same options here as well (in fact you don't need JSON active normally):

<img src="https://user-images.githubusercontent.com/148430940/276643580-279b40c7-6585-45ed-a2b1-e9b5d2ba6230.jpg" width="400">
<img src="https://user-images.githubusercontent.com/148430940/276643781-d7bb6539-c6a2-42d1-b7f8-f0d45cd3d881.jpg" width="400">

## CHECK CONNECTION

Now if you go check the following webpage ```http://YOUR_BOILER_IP_ADDRESS/logfiles/pelletronic/```, you should see something like this:
![image](https://github.com/Domotrique/okovision_2023/assets/148430940/3b6a26d9-4499-43f6-8505-53ded15d6c5b)

If you see this page, you've done the hardest part!

<img src="https://user-images.githubusercontent.com/148430940/276651209-10c7936f-aa83-47ab-a2ec-c3e727c193df.jpg" width="400">

## OKOVISION SETUP

Now, I invite you to check the very well explained documentation from @stawen [here](https://okovision.dronek.com/documentation/configuration/setup/)

## AND NOW?

If you see something not working as expected, please create an issue and I will try to have a look as soon as possible.
