
FROM docker.dbc.dk/dbc-jessie

RUN echo "deb http://debian.dbc.dk/debian-dbc jessie-dbc-proposed main contrib non-free" >> /etc/apt/sources.list.d/dbc-proposed.list

#
# when done developing the apt package list
# use apt-install on one line
# RUN apt-install apache2 php5 php5-curl php5-pgsql php5-oci8 vim dbmfappl-dbc-2013-5 paf2-dbc-2013-5 
#
RUN apt-get update
#RUN apt-get install -y apache2 php5 php5-curl php5-pgsql php5-oci8 vim java8-jdk
#RUN apt-get install -y paf-dbc-2013-5
RUN apt-install apache2 php5 php5-curl php5-pgsql php5-oci8 vim java8-jdk paf-dbc-2013-5

COPY DigitalResources.ini /var/www/html
COPY DigitalResources /var/www/html/DigitalResources
#COPY eVALU /var/www/html/eVALU
#COPY classes /var/www/html/classes
COPY inc /var/www/html/inc
COPY UnitTests /var/www/html/UnitTests
COPY paf /var/www/html/paf
RUN cd /var/www/html


EXPOSE 80/tcp
ENTRYPOINT "/bin/bash" "-c" "source /etc/apache2/envvars && /usr/sbin/apache2 -D FOREGROUND"
