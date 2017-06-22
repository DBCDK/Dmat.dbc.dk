
FROM docker.dbc.dk/dbc-jessie

RUN echo "deb http://debian.dbc.dk/debian-dbc jessie-dbc-proposed main contrib non-free" >> /etc/apt/sources.list.d/dbc-proposed.list

#
# when doen developong the apt package list
# use apt-install on one line
# RUN apt-install apache2 php5 php5-curl php5-pgsql php5-oci8 vim dbmfappl-dbc-2013-5 paf2-dbc-2013-5 
#
RUN apt-get update
#RUN apt-get install -y apache2 vim java8-jdk
RUN apt-install  vim java8-jdk
expose 8080/tcp

COPY wiremock /home/wiremock
ENTRYPOINT "/bin/bash" "-c" "cd /home/wiremock; java -jar wiremock-standalone-2.6.0.jar --verbose >> wiremock.log"
