/**
 *  @mainpage Documentation for "posthus"
 *
 * \section intro_sec Introduction
 *
 * It is only the "Digitalresources" that is deployed.
 *
 * Documentation for the rest will be made when deployed.
 *  DUMMY dummy
 * 
 * @author Hans-Henrik Lund
 * @date 03-01-2012
 */

--------------------------------------------------------------------------------------------------
git flow noter:

NB!   Man skal have sat følgende:
    File -> Settings -> Other Settings -> Gitflow -> Releases -> flueben i "Push on finish release"


1) Start Feature : NAVN
        Laver en LOKAL branch med navn "feature/NAVN" udfra de data der ligger i Intellij her og nu.
2) Programmer og test
3) "grøn pil op" VCS - commit og push
        feature branch bliver comitted og oprettet på git.dbc.dk
4) gå til 2 til man er tilfreds.
5) Finish Feature
        sletter feature/NAVN lokalt og på git.dbc.dk - har merged rettelserne ned i lokal "develop"
6) Push develop til git.dbc.dk (VCS -> Git -> Push)  -- KAN UDELADES DA step 8 søger for at opdatere master og develop
7) Start Release - TAGNAME
        laver lokal branch med navn release/TAGNAME
8) Finish Release
        merger release ind i master og develop (begge lokale) og opdaterer (push'er) master og develop på git.dbc.dk

Ny feature kan startes


---------------------------------------------------------------------------------------------------
 Nyttige docker ordre:

 docker images - se hvilke images der findes
 docker rmi  xxx - fjern et eller flere images
 docker build  -t dmat:hhl --force-rm .  - byg et nyt image

 d

 docker ps  - vis mine active docker containere
 docker ps -a - vis alle containere
 docker stop dmatHHL  - stop container
 docker rm dmatHHL - fjern contianer
 docker kill dmatHHL - svarer til kill -9

 docker exec -ti dmatHHL /bin/bash  - kom ind i den kørende container


 filen ~hhl/.docker/config.json  ser således ud:
 {
     "auths": {
         "docker-d.dbc.dk": {
		      "auth": "aGhsOlN0b2NraG9sbTIwMTc="
	     },
	     "docker.dbc.dk": {
	          "auth": "aGhsOlN0b2NraG9sbTIwMTc="
	     },
	     "tdocker.dbc.dk": {
	          "auth": "aGhsOlRhbnphbmlhMjAxNw=="
	     }
    }
}


docker login https://docker.dbc.dk  - opretter ovenstående - windows passwd skal bruges


Postgres:
Start:
    docker run --name postgresHHL -p 1950:5432 docker.dbc.dk/dbc-postgres
eller
    docker run --detach -p 2612:80 -v /home/hhl/public_html/Dmat.dbc.dk/aplog:/var/log/apache2 -v /home/hhl/public_html/Dmat.dbc.dk/eVALU:/var/www/html/eVALU --name dmatHHL dmat:hhl
Hvor --detach får den til at køre i bagrunden og -v mapper dirs mellem docker og værtsmaskinen

Connect:
psql -h devel7.dbc.dk -p 1950 -U db_user postgres

(password = db_password)


Nyttige git ordrer:

git branch  - se hvilke branches der er lokalt
git branch -a  - se alle branches
git checkout u15 - checkout u15, hvis den findes lokalt brug den ellers tag fra git.dbc.dk
git push remote --delete u15   - slet branch på git.dbc.dk
git branch -d u15 -  slet lokalt
git config credential.helper store  - gem credentials


Git Ændringer via IntelliJ
--------------------------

Checkout develop: VCS | Git | Branches
Pull
Lav branch: VCS | Git | Branches - New Branch (XXX-nnn)
Lav ændringer (nu under Feature branchen som angivet)
Commit: (Pil op) - så mange gange at man ønsker...
Commit & Push: (Pil op)
Merge branch:
 - VCS | Git | Branches - Checkout develop
 - VCS | Git | Pull
 - VCS | Git | Merge changes
 - VCS | Git | Push (hak i Feature branch navnet)


Brug af wiremock
----------------

Da testsystemet slår op i saxo's hjemmeside er der en wiremock opsætning for denne adresse.
start wiremock:

cd UnitTests/wiremock
java -jar wiremock-standalone-2.6.0.jar --record-mappings --verbose --proxy-all="https://www.saxo.com"

Kald via wiremock:
curl "http://localhost:8080/dk/soeg/boeger?query=Livets%20kamp%20Charles%20Dickens"

Hvis man gentager ovenstående tager den fra wiremock's filer istedet for fra saxo, selvom den er i "record-mappings"


Søgning i raapost;
-------------------
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://oss.dbc.dk/ns/opensearch">
  <SOAP-ENV:Body>
    <ns1:searchRequest>
      <ns1:query>marc.245a=danmark</ns1:query>
      <ns1:agency>100200</ns1:agency>
      <ns1:profile>test</ns1:profile>
      <ns1:start>1</ns1:start>
      <ns1:stepValue>10</ns1:stepValue>
<ns1:repository>prod-rawrecords</ns1:repository>
    </ns1:searchRequest>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>

