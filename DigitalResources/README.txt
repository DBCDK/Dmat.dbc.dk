/**
 *
 * This file is part of Open Library System.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * Open Library System is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Open Library System is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Open Library System.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 *  @page Documentation Documentation for the scripts used for Digitalresources
 *
 * \section intro_sec Introduction
 *
 * This documentation is in Danish.
 *
Overordnet er DigitalResources en samling programmer der tilsammen gør at data
fra eksterne leverandører af digitale resourcer, giver os adgang til at hente
metadata om disse og lade dem indgå i vores processer.

Vi har i øjeblikket 2 leverandører. Begge styret af Publizon.  Netlydbøger:eLib og
eReolen:Pubhub.

Et flow for en digital resource ser således ud:
  - hent metadata fra poster (siden sidst)
  - tildel faustnummer (enten nyt eller find det gamle fra basis)
  - send metadata som marcposter til brønden (well)
  - send xml til brønden for indlæggelse af forlags noter
  - send metadata som marcposter til basis (p-huset)
  - hent forsider og læg dem i forsideservice

Hvilke af ovennævnte der bliver udført for en digital resource bliver styret
af filen "RunDaily.php".
Hvornår den bliver kørt er styret af crontab (bruger danbib).

RunDaily.php indgår ikke direkte i svn. Den indgår som en skabelon "RunDaily.php_INSTAL".

Denne fil skal man kopiere over til RunDaily.php.

Dette for ikke at lave om i kørslen hvis man opdatere med en ny version af programpakken.

De samme overvejelser gælder DigitalResources.ini_INSTALL.

DigitalResources.ini indeholder data nødvendige for afvikling af scriptene.
Der findes i DigitalResources.ini både en [pubhub] og en [pubhubDelete] section.
Den sidste fordi at Publizon har valgt at lave 2 forskellige web-services til
poster og til delete poster.

 *
 * RunDaily.php er det script der jævntligt skal køre. Man kan køre det så
 * ofte man har lyst, dog må det ikke være med intervaller under
 *  1 time, for at være sikker på at det foregåendejob kan nå
 * at blive færdig.
 *
 * Følgende job bliver kørt i denne rækkefølge:
 *   - getXmlsFromPubHub.php
 *   - UpdateFaustFromBasis.php
 *   - insertFaust.php
 *   - ToBasis.php
 *   - ToWell.php -t eReolenToWell
 *   - ToWell.php -t NetlydbogToWell
 *   - ToWell.php -t PubHubXMLtoWell
 *   - UploadFrontPageImages.php -t PubHubImages
 *
 * <h2>getXmlsFromPubHub.php</h2>
 *
 * Jobbet starter med at logge sig ind i den Postgres database som er angivet i posthus.ini filen.
 * Hvis tabellen 'digitalresources' ikke findes oprettes den.
 *
 * Hvis man logger ind med pgAdmin (credentials kan ses i Digitalresources.ini) kan man
 * se hvordan tabellen er bygget op.
 *
 * Programmet henter en XML fil ned fra Pubhuibs API (web-service).  Det er alle
 * poster man henter. Både e-bøger og lydbøger.
 *
 * Hver <product> element i den samlede XML-fil bliver lavet om til en XML-post.
 *
 * <external_id><isbn13> bliver udtaget af posten. Hvis posten kun har et isbn (10) vil dette
 * blive lavet om til isbn13.
 *
 * Hvilken type posten er (ebog eller lydbog) bliver afgjort udfra <format_id>. Hvis dette enten er 71, 75
 * eller 230 er det en lydbog ellers en ebog.
 *
 * Titlen bliver udtaget.
 *
 * Programmet undersøger om der findes en post i databasen med samme ISBN13 og samme "provider".
 *
 * Hvis der gør sker der ikke yderligere, ellers vil der blive oprettet en post (række) i tabellen
 * digitalresources.
 *
 * Felterne faust, sent_to_basis, sent_to_well, sent_xml_to_well, cover_status og sent_to_covers vil ikke være udfyldt.
 *
 * Ændringer i XML fra Pubhub på en allerede registreret post i tabellen digitalresources vil ikke blive opdateret.
 *
 * Der bliver også hentet isbn13 fra pubhub: GetRemovedProductList.  Dette er poster der ikke længere skal være en del af
 * Netlydbog/eReolen.  Parameteren status, og delete_date bliver opdateret i digitalresources tabellen.
 *
 * Det omvendte, posten har status "d" og den kommer under "GetProductList", vil afstedkomme at "d" ændres til "n".
 *
 * <h2>UpdateFaustFromBasis.php</h2>
 *
 * Programmet tager alle isbn13 i tabellen digitalresources, som IKKE har noget faust nummer, og undersøger om der i basis
 * findes en post med det isbn13/isbn10 som en angivet i tabellen. Faust (samme som lokalid) fra basis bliver indsat i tabellen.
 *
 * Hvis der findes en post og denne er en angivet som trykt materiale bliver faust ikke indsat.

 * <h2>insertFaust.php</h2>
 *
 * insertFaust.php finder alle indgange i tabellen der ikke har noget faust-nummer.  For hver af disse
 * bliver der trukket et faustnummer fra basis (Rck_send) og indsat i tabellen.
 *
 * Når dette punkt er færdig vil alle indgange i tabellen have et faust nummer.  Gensendelse til basis
 * etc. vil ikke ændre på faust-nummeret.
 *
 * <h2>ToBasis.php</h2>

Programmet tager alle poster i tabellen, der ikke har en sent_to_basis dato og gennererer en marcpost.

Programmet undersøger også om den pågældende post allerede findes i basis (samme faust).

Hvis den findes bliver posten (direkte i basis) opdateret med ugekoderne ERE/NLY og DAT.  865 etc. bliver også opdateret.

Hvis den ikke findes bliver den gennererede post uploaded til Phuset.

Er det en post med status "d" bliver der fjernet ugekoder etc. hvis posten findes i basis. Ellers ingen handling.

Uploaded sker med programmet Rck_send.

<h2>ToWell.php</h2>

Programmet skal forsynes med en parameter der fortæller programmet hvilken upload der er tale om.  I skrivende stunde findes:

   * PubHubXmlToWell
   * eReolenToWell
   * NetlydbogToWell

Eksempel på en indgang i Digitalresources:

<pre>
[eReolenToWell]
datafile  = "150028.$ts.data"
transfile = "150028.$ts.truns"
transline = "b=databroendpr2,f=$datafile,t=dm2iso,c=latin-1,o=ebogsbib,m=kildepost@dbc.dk"
provider = Pubhub
format = eReolen
outputformat = eReolenWell
sent_to = sent_to_well
</pre>

Provider er indgange i digitalresources tabellen, så her vil det kun være poster med provider='Pubhub' og format='eReolen'

outputformat=eReolenWell, skal være defineret i classen: ConvertXmlToMarc_class.php

sent_to = sent_to_well er den indgang i digitalresources som skal opdateret med d.d.

Er det en post med status "d", vil marcposten også får status "d".

Ved upload af XML, bliver hver post pakket ind i en XML wrapper.  Dette for at vi kan tilføje faust og status.

<h2>UploadFrontPageImages.php</h2>

Programmet tager de poster der ikke har en sent_to_covers dato sat.  Programmet undersøger om posten findes i basis (faust nummer). Hvis
den ikke findes vil der blive indsat en tekst i cover_status "Faust not in database (<em>dato</em>)".  Næste gang programmet kører bliver
der testet for om posten skulle være dukket op i basis.

Opstår der fejl i konvertering/upload til forsideservice bliver der skrevet en fejlmeddelses i cover_status.

Poster med status 'd' bliver ikke behandlet af programmet.

<h1>Drift dokumentation</h1>

Programmerne bruger en tabel i en postgresdatabase:  digitalresources

Denne indeholder al information.

Desuden forespørger programmet i Basis og uploader marc-poster til Basis, Phuset og brønden.

Logning sker i en log-fil.  Hvis en logning starter med "ERROR" skal følgende underrettes:

      - Hans-Henrik
      - Kurt Poulssen
      - Karin Knudsen
      - Stine Kjellerup Weymann

Al opsætning foregår i Digitalresource.ini

Systemet kører på Kiska under bruger Danbib

Programmerne ligger i svn:  https://svn.dbc.dk/repos/php/Projects/posthus/trunk


 * @todo  Lav en analyse af hvilke elementer i XML fra Pubhub der skal afstedkomme handlinger og hvilke handlinger der skal ske.
eks.  Har URL'en til images ændret sig skal der uploades et nyt billede til forsideservice.

 *
 * @author Hans-Henrik Lund
 * @date 29-07-2011
 * @date 03-01-2012
 * @date 13-06-2012
 */
