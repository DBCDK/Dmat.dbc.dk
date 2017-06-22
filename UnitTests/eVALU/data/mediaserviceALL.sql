--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

SET search_path = public, pg_catalog;

DROP SEQUENCE if exists mediaserviceseq;
CREATE SEQUENCE mediaserviceseq start 1000;

DROP SEQUENCE if exists mediaservicerecoverseq;
CREATE SEQUENCE mediaservicerecoverseq;

ALTER TABLE ONLY public.mediaservicerecover DROP CONSTRAINT recover_pkey;
DROP TABLE public.mediaservicerecover;
SET search_path = public, pg_catalog;

SET default_with_oids = false;

--
-- Name: mediaservicerecover; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE mediaservicerecover (
recoverseqno integer NOT NULL,
recovercreatedate timestamp with time zone,
seqno integer,
status character varying(20),
update timestamp with time zone,
faust character varying(11),
newfaust character varying(11),
base character varying(10),
choice character varying(30),
promat timestamp with time zone,
initials character varying(20),
program character varying(250)
);

--
-- Data for Name: mediaservicerecover; Type: TABLE DATA; Schema: public; Owner: -
--
COPY mediaservicerecover (recoverseqno, recovercreatedate, seqno, status, update, faust, newfaust, base, choice, promat, initials, program) FROM stdin;
\.

--
-- Name: recover_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--
ALTER TABLE ONLY mediaservicerecover
ADD CONSTRAINT recover_pkey PRIMARY KEY (recoverseqno);


DROP INDEX public.providerisbn13_idx;
DROP INDEX public.bookid_idx;
ALTER TABLE ONLY public.mediaservice DROP CONSTRAINT mediaservice_pkey;
ALTER TABLE ONLY public.mediaservicenote DROP CONSTRAINT mediaservicdnote_pkey;
DROP TABLE public.mediaservicenote;
DROP TABLE public.mediaservicedref;
DROP TABLE public.mediaservice;
SET search_path = public, pg_catalog;

SET default_with_oids = false;

--
-- Name: mediaservice; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE mediaservice (
    seqno integer NOT NULL,
    status character varying(20),
    provider character varying(50),
    createdate timestamp with time zone,
    update timestamp with time zone,
    booktype character varying(50),
    filetype character varying(25),
    bookid character varying(50),
    title character varying(100),
    originalxml text,
    isbn13 character varying(13),
    publicationdate timestamp with time zone,
    faust character varying(11),
    newfaust character varying(11),
    base character varying(10),
    promat timestamp with time zone,
    choice character varying(30),
    initials character varying(20),
    lockdate timestamp with time zone,
    checksum bigint
);


--
-- Name: mediaservicedref; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE mediaservicedref (
    seqno integer,
    createdate timestamp without time zone,
    base character varying(50),
    lokalid character varying(20),
    bibliotek character varying(20),
    type character varying(20),
    matchtype integer
);


--
-- Name: mediaservicenote; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE mediaservicenote (
    seqno integer NOT NULL,
    createdate timestamp with time zone,
    updated timestamp with time zone,
    type character varying(20),
    text character varying(500) NOT NULL,
    initials character varying(20),
    status character varying(10)
);


--
-- Data for Name: mediaservice; Type: TABLE DATA; Schema: public; Owner: -
--

COPY mediaservice (seqno, status, provider, createdate, update, booktype, filetype, bookid, title, originalxml, isbn13, publicationdate, faust, newfaust, base, promat, choice, initials, lockdate, checksum) FROM stdin;
26241	eVa	PubHubMediaService	2015-07-11 04:22:03.600687+02	2015-07-14 04:28:02.41714+02	Ebog	epub	a28bc40e-54bc-4de6-ac87-f845ac492c9a	Adfærdsdesign 2	<?xml version="1.0"?>\n<root>\n  <Book xmlns="http://service.pubhub.dk/">\n    <BookType>Ebog</BookType>\n    <FileType>epub</FileType>\n    <BookId>a28bc40e-54bc-4de6-ac87-f845ac492c9a</BookId>\n    <Identifier>9788799807710</Identifier>\n    <IdentifierType>ISBN13</IdentifierType>\n    <Title>Adf&#xE6;rdsdesign 2</Title>\n    <SubTitle>Borgerne i samfundet</SubTitle>\n    <Language>dan</Language>\n    <PublicationDate>10-07-2015</PublicationDate>\n    <PublisherName>/KL. 7</PublisherName>\n    <PartNumber>2</PartNumber>\n    <NameOfBookSeries>Adf&#xE6;rdsdesign</NameOfBookSeries>\n    <MainDescription>I bog 2 i serien om adf&#xE6;rdsdesign, stiller vi skarpt p&#xE5;, hvordan myndigheder kan anvende adf&#xE6;rdsdesign til at p&#xE5;virke borgernes adf&#xE6;rd i en positiv og b&#xE6;redygtig retning. Ja endog hvordan adf&#xE6;rdsdesign kan mindske kriminalitet og lovovertr&#xE6;delser. Endelig ser vi p&#xE5; de s&#xE5;kaldte livsstilssygdomme og kommer med bud p&#xE5;, hvordan vi i h&#xF8;jere grad kan spille sammen med vores biologi fremfor i mod, n&#xE5;r vi k&#xE6;mper for at leve sundere. God l&#xE6;selyst.</MainDescription>\n    <DigitalProtection>DigitalVandmaerkning</DigitalProtection>\n    <Price CurrencyCode="DKK">39.2</Price>\n    <RecommendedPrice CurrencyCode="DKK">49</RecommendedPrice>\n    <Contributors>\n      <Contributor>\n        <Id>67772</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Mikkel Holm</NamesBeforeKey>\n        <KeyNames>S&#xF8;rensen</KeyNames>\n        <BiographicalNote/>\n        <Websites/>\n      </Contributor>\n      <Contributor>\n        <Id>67773</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Simon</NamesBeforeKey>\n        <KeyNames>Bentholm</KeyNames>\n        <BiographicalNote/>\n        <Websites/>\n      </Contributor>\n      <Contributor>\n        <Id>67774</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Sebastian Borum</NamesBeforeKey>\n        <KeyNames>Olsen</KeyNames>\n        <BiographicalNote/>\n        <Websites/>\n      </Contributor>\n      <Contributor>\n        <Id>67775</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Christian</NamesBeforeKey>\n        <KeyNames>M&#xF8;lgaard</KeyNames>\n        <BiographicalNote/>\n        <Websites/>\n      </Contributor>\n      <Contributor>\n        <Id>67776</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Clara</NamesBeforeKey>\n        <KeyNames>Zeller</KeyNames>\n        <BiographicalNote/>\n        <Websites/>\n      </Contributor>\n    </Contributors>\n    <FileSize>579 KB</FileSize>\n    <FileVersion/>\n    <BookFormat>Reflowable</BookFormat>\n    <SubscriptionSaleAllowed>true</SubscriptionSaleAllowed>\n    <Authors>Mikkel Holm S&#xF8;rensen, Simon Bentholm, Sebastian Borum Olsen, Christian M&#xF8;lgaard, Clara Zeller</Authors>\n    <SampleUrl>http://samples.pubhub.dk/9788799807710.epub</SampleUrl>\n    <RightList/>\n    <Subjects>\n      <SimpleSubject>\n        <Code>JML</Code>\n        <Description>Eksperimentel psykologi</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>JMAL</Code>\n        <Description>Adf&#xE6;rdsm&#xE6;ssig teori (behaviorisme)</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>GPQ</Code>\n        <Description>Beslutningsteori: generelt</Description>\n      </SimpleSubject>\n    </Subjects>\n    <Images>\n      <Image Type="Forside">https://images.pubhub.dk/originals/a28bc40e-54bc-4de6-ac87-f845ac492c9a.jpg</Image>\n      <Image Type="ForsideMiniature">https://images.pubhub.dk/thumbnails/a28bc40e-54bc-4de6-ac87-f845ac492c9a.jpg</Image>\n    </Images>\n    <PriceBeforeDiscount CurrencyCode="DKK">39.2</PriceBeforeDiscount>\n  </Book>\n</root>\n	9788799807710	2015-07-10 00:00:00+02	\N	\N	\N	\N	\N	hhl	2015-07-13 16:49:28.505584+02	1307903407
26257	eVa	PubHubMediaService	2015-07-14 04:15:52.867703+02	2015-07-14 04:27:33.641523+02	Ebog	epub	94ae9eef-7268-40cc-8965-77677afcd931	Sukkerfri børnefest	<?xml version="1.0"?>\n<root>\n  <Book xmlns="http://service.pubhub.dk/">\n    <BookType>Ebog</BookType>\n    <FileType>epub</FileType>\n    <BookId>94ae9eef-7268-40cc-8965-77677afcd931</BookId>\n    <Identifier>9788702176353</Identifier>\n    <IdentifierType>ISBN13</IdentifierType>\n    <Title>Sukkerfri b&#xF8;rnefest</Title>\n    <SubTitle>Is, kager, desserter og s&#xF8;de sager</SubTitle>\n    <PublicationDate>17-08-2015</PublicationDate>\n    <Edition>Edition 1</Edition>\n    <ImprintName>Gyldendal</ImprintName>\n    <PublisherName>Gyldendal</PublisherName>\n    <MainDescription>&lt;p&gt;&lt;strong&gt;Vil du gerne fork&#xE6;le dit barn - men undg&#xE5; tilsat sukker? S&#xE5; har du fundet den rigtige bog.&lt;/strong&gt;&lt;/p&gt;\n\n&lt;p&gt;&lt;em&gt;&#x2019;SUKKERFRI B&#xD8;RNEFEST&#x2019;&lt;/em&gt; er en glad kogebog fyldt med sjove opskrifter til fest, f&#xF8;dselsdag og n&#xE5;r der skal hygges hjemme i sofaen, i skolen eller b&#xF8;rnehaven. Der er enkle opskrifter, som b&#xF8;rn kan lave p&#xE5; egen h&#xE5;nd &#x2013; og nogle, som I skal lave sammen. Men f&#xE6;lles for dem er, at det hvide sukker er skiftet ud med sundere, naturlige alternativer.&lt;/p&gt;\n\n&lt;p&gt;Bogen indeholder opskrifter p&#xE5; alt fra boller &amp; varm kakao til is, smoothies, sm&#xE5; &amp; store kager og masser af s&#xF8;de sager. Bogen henvender sig b&#xE5;de til for&#xE6;ldre og bedstefor&#xE6;ldre, men ogs&#xE5; til institutioner, der har en &#x2019;nul sukker politik&#x2019;. Opskrifterne kan nemlig let ganges op, s&#xE5; der er nok til alle.&lt;/p&gt;\n\n&lt;p&gt;&lt;em&gt;DITTE INGEMANN&lt;/em&gt; er bachelor i ern&#xE6;ring og sundhed og st&#xE5;r bag Danmarks popul&#xE6;reste blogs The Food Club. Hun er desuden forfatter til &#x2019;Low carb fra The Food Club&#x2019;, &#x2019;Sunde s&#xF8;de sager fra The Food Club&#x2019; og &#x2019; Salater fra The Food Club&#x2019;.&lt;/p&gt;\n\n&lt;p&gt;Ps. De voksne m&#xE5; ogs&#xE5; godt smage!&lt;/p&gt;</MainDescription>\n    <DigitalProtection>DigitalVandmaerkning</DigitalProtection>\n    <Price CurrencyCode="DKK">60</Price>\n    <Contributors>\n      <Contributor>\n        <Id>67792</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Ditte</NamesBeforeKey>\n        <KeyNames>Ingemann</KeyNames>\n        <Websites>\n          <Website>\n            <Id>b79e9ffd-3857-46ec-ba8a-d58f7ae32895</Id>\n            <WebsiteRole>7</WebsiteRole>\n            <WebsiteLink>http://www.gyldendal.dk/Ditte-Ingemann+</WebsiteLink>\n          </Website>\n        </Websites>\n      </Contributor>\n    </Contributors>\n    <FileSize>23645 KB</FileSize>\n    <FileVersion>2.0</FileVersion>\n    <BookFormat>Reflowable</BookFormat>\n    <SubscriptionSaleAllowed>true</SubscriptionSaleAllowed>\n    <Authors>Ditte Ingemann</Authors>\n    <SampleUrl>http://samples.pubhub.dk/9788702176353.epub</SampleUrl>\n    <RightList/>\n    <Subjects>\n      <SimpleSubject>\n        <Code>VF</Code>\n        <Description>Familie og sundhed</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>WBQ</Code>\n        <Description>Madlavning for / med b&#xF8;rn</Description>\n      </SimpleSubject>\n    </Subjects>\n    <Images>\n      <Image Type="Forside">http://images.pubhub.dk/originals/94ae9eef-7268-40cc-8965-77677afcd931.jpg</Image>\n      <Image Type="ForsideMiniature">http://images.pubhub.dk/thumbnails/94ae9eef-7268-40cc-8965-77677afcd931.jpg</Image>\n    </Images>\n    <PriceBeforeDiscount CurrencyCode="DKK">60</PriceBeforeDiscount>\n  </Book>\n</root>\n	9788702176353	2015-08-17 00:00:00+02	\N	\N	\N	\N	\N	\N	\N	4155865659
26259	eVa	PubHubMediaService	2015-07-14 04:16:36.932554+02	2015-07-14 04:27:27.396867+02	Ebog	epub	ff704bc8-9c65-4f0b-8b65-a840c5fd5463	H. Rider Haggards Kong Salomons miner	<?xml version="1.0"?>\n<root>\n  <Book xmlns="http://service.pubhub.dk/">\n    <BookType>Ebog</BookType>\n    <FileType>epub</FileType>\n    <BookId>ff704bc8-9c65-4f0b-8b65-a840c5fd5463</BookId>\n    <Identifier>9788702173406</Identifier>\n    <IdentifierType>ISBN13</IdentifierType>\n    <Title>H. Rider Haggards Kong Salomons miner</Title>\n    <PublicationDate>14-08-2015</PublicationDate>\n    <Edition>Edition 1</Edition>\n    <ImprintName>Gyldendal</ImprintName>\n    <PublisherName>Gyldendal</PublisherName>\n    <MainDescription>&lt;p&gt;Gyldendals Ud&#xF8;delige. De helt store klassikere, opdateret med k&#xE6;rlig h&#xE5;nd af levende anerkendte danske b&#xF8;rnebogsforfattere.&lt;br /&gt;\n&lt;br /&gt;\nGyldendals ud&#xF8;delige lever. Rigtige historier med rigtige helte og rigtige skurke. Her er timevis af sjov, sp&#xE6;nding og d&#xF8;delig alvor med Long John Silver, Robin Hood, Phileas Fogg, Kaptajn Nemo og alle de andre ud&#xF8;delige venner og fjender. Det g&#xE6;lder liv og d&#xF8;d dybt under havet, alt eller intet p&#xE5; en &#xF8;de trope&#xF8;, k&#xE6;rlighed og opr&#xF8;r i Sherwoodskoven, krig og n&#xF8;d i Sibirien og i Nordalaskas &#xF8;demark.&lt;/p&gt;\n\n&lt;p&gt;P&#xE5; et skib fra Cape Town til Durban m&#xF8;der storvildtsj&#xE6;geren Allan Quartermain sir Henry og kaptajn Good. De er i Sydafrika for at lede efter sir Henrys bror, som er taget ud for at finde Kong Salomons sagnomspundne diamantminer. Quartermain slutter sig til selskabet, og de tre m&#xE6;nd drager ud p&#xE5; en lang og halsbr&#xE6;kkende f&#xE6;rd. Undervejs bliver de rodet ind i kampen mod den onde Kong Twala, og da de endelig n&#xE5;r frem til minerne og begejstret begraver h&#xE6;nderne i bunker af smukke sten, g&#xE5;r det op for dem, at de er blevet narret i en f&#xE6;lde ...&lt;/p&gt;</MainDescription>\n    <DigitalProtection>DigitalVandmaerkning</DigitalProtection>\n    <Price CurrencyCode="DKK">60</Price>\n    <Contributors>\n      <Contributor>\n        <Id>67799</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Astrid</NamesBeforeKey>\n        <KeyNames>Heise-Fjeldgren</KeyNames>\n        <Websites>\n          <Website>\n            <Id>30d02dc4-85a8-4322-bbbf-abb1741be7bc</Id>\n            <WebsiteRole>7</WebsiteRole>\n            <WebsiteLink>http://www.gyldendal.dk/Astrid-Heise-Fjeldgren</WebsiteLink>\n          </Website>\n        </Websites>\n      </Contributor>\n    </Contributors>\n    <FileSize>561 KB</FileSize>\n    <FileVersion>2.0</FileVersion>\n    <BookFormat>Reflowable</BookFormat>\n    <SubscriptionSaleAllowed>true</SubscriptionSaleAllowed>\n    <Authors>Astrid Heise-Fjeldgren</Authors>\n    <SampleUrl>http://samples.pubhub.dk/9788702173406.epub</SampleUrl>\n    <RightList/>\n    <Subjects>\n      <SimpleSubject>\n        <Code>YFA</Code>\n        <Description>Klassisk sk&#xF8;nlitteratur (b&#xF8;rn og unge)</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>5AL</Code>\n        <Description>L&#xE6;sealder fra ca. 10 &#xE5;r</Description>\n      </SimpleSubject>\n    </Subjects>\n    <Images>\n      <Image Type="Forside">http://images.pubhub.dk/originals/ff704bc8-9c65-4f0b-8b65-a840c5fd5463.jpg</Image>\n      <Image Type="ForsideMiniature">http://images.pubhub.dk/thumbnails/ff704bc8-9c65-4f0b-8b65-a840c5fd5463.jpg</Image>\n    </Images>\n    <PriceBeforeDiscount CurrencyCode="DKK">60</PriceBeforeDiscount>\n  </Book>\n</root>\n	9788702173406	2015-08-14 00:00:00+02	\N	\N	\N	\N	\N	\N	\N	3773343032
26262	eVa	PubHubMediaService	2015-07-14 04:25:22.360801+02	2015-07-14 04:27:20.762238+02	Ebog	epub	96675f7f-32c1-4e62-9209-d34b7dcad4f7	Mark Twains Huckleberry Finn	<?xml version="1.0"?>\n<root>\n  <Book xmlns="http://service.pubhub.dk/">\n    <BookType>Ebog</BookType>\n    <FileType>epub</FileType>\n    <BookId>96675f7f-32c1-4e62-9209-d34b7dcad4f7</BookId>\n    <Identifier>9788702169942</Identifier>\n    <IdentifierType>ISBN13</IdentifierType>\n    <Title>Mark Twains Huckleberry Finn</Title>\n    <PublicationDate>14-08-2015</PublicationDate>\n    <Edition>Edition 1</Edition>\n    <ImprintName>Gyldendal</ImprintName>\n    <PublisherName>Gyldendal</PublisherName>\n    <MainDescription>&lt;p&gt;Den frihedshungrende Huck har fingeret sit eget d&#xF8;dsfald for at slippe v&#xE6;k fra sin voldelige og fordrukne far. P&#xE5; sin flugt ned ad Mississippi-floden m&#xF8;der han slaven Jim, der er flygtet fra sin ejer og har dus&#xF8;rj&#xE6;gere p&#xE5; nakken. Sammen sejler de to ned ad floden p&#xE5; en t&#xF8;mmerfl&#xE5;de, og et venskab udvikler sig p&#xE5; denne ikke helt ufarlig rejse med b&#xE5;de tyveri, svindel, mordere, smukke piger og seje fyre.&lt;/p&gt;</MainDescription>\n    <DigitalProtection>DigitalVandmaerkning</DigitalProtection>\n    <Price CurrencyCode="DKK">60</Price>\n    <Contributors>\n      <Contributor>\n        <Id>67800</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Lene</NamesBeforeKey>\n        <KeyNames>M&#xF8;ller J&#xF8;rgensen</KeyNames>\n        <Websites>\n          <Website>\n            <Id>88138cd4-1aa9-4f7c-a973-c753d52d3896</Id>\n            <WebsiteRole>7</WebsiteRole>\n            <WebsiteLink>http://www.gyldendal.dk/Lene-M%c3%b8ller+J%c3%b8rgensen</WebsiteLink>\n          </Website>\n        </Websites>\n      </Contributor>\n    </Contributors>\n    <FileSize>2654 KB</FileSize>\n    <FileVersion>2.0</FileVersion>\n    <BookFormat>Reflowable</BookFormat>\n    <SubscriptionSaleAllowed>true</SubscriptionSaleAllowed>\n    <Authors>Lene M&#xF8;ller J&#xF8;rgensen</Authors>\n    <SampleUrl>http://samples.pubhub.dk/9788702169942.epub</SampleUrl>\n    <RightList/>\n    <Subjects>\n      <SimpleSubject>\n        <Code>YFB</Code>\n        <Description>Sk&#xF8;nlitteratur (b&#xF8;rn og unge)</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>YFA</Code>\n        <Description>Klassisk sk&#xF8;nlitteratur (b&#xF8;rn og unge)</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>YFC</Code>\n        <Description>Eventyrsfort&#xE6;llinger (b&#xF8;rn og unge)</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>5AJ</Code>\n        <Description>L&#xE6;sealder fra ca. 8 &#xE5;r</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>5AK</Code>\n        <Description>L&#xE6;sealder fra ca. 9 &#xE5;r</Description>\n      </SimpleSubject>\n    </Subjects>\n    <Images>\n      <Image Type="ForsideMiniature">http://images.pubhub.dk/thumbnails/96675f7f-32c1-4e62-9209-d34b7dcad4f7.jpg</Image>\n      <Image Type="Forside">http://images.pubhub.dk/originals/96675f7f-32c1-4e62-9209-d34b7dcad4f7.jpg</Image>\n    </Images>\n    <PriceBeforeDiscount CurrencyCode="DKK">60</PriceBeforeDiscount>\n  </Book>\n</root>\n	9788702169942	2015-08-14 00:00:00+02	\N	\N	\N	\N	\N	\N	\N	1669152082
26258	eVa	PubHubMediaService	2015-07-14 04:16:12.292438+02	2015-07-14 04:27:30.585557+02	Ebog	epub	70693d78-d150-4fdc-950b-8ce1e6586857	Rideskolemysteriet	<?xml version="1.0"?>\n<root>\n  <Book xmlns="http://service.pubhub.dk/">\n    <BookType>Ebog</BookType>\n    <FileType>epub</FileType>\n    <BookId>70693d78-d150-4fdc-950b-8ce1e6586857</BookId>\n    <Identifier>9788793197428</Identifier>\n    <IdentifierType>ISBN13</IdentifierType>\n    <Title>Rideskolemysteriet</Title>\n    <Language>dan</Language>\n    <PublicationDate>13-07-2015</PublicationDate>\n    <Edition>1</Edition>\n    <PublisherName>Candied Crime</PublisherName>\n    <MainDescription>Trine er hestepige. Hun elsker at ride p&#xE5; den dejlige pony Molly. &#x201D;Du lugter s&#xE5; godt,&#x201D; hvisker Trine til Molly, mens Molly f&#xE5;r et hestebolsje. Trine er helt sikker p&#xE5;, at Molly forst&#xE5;r hende.\nMen da Trine cykler hjem, ser hun en campingvogn, der er gemt inde i skoven. Det er mystisk, synes Trine. \nN&#xE6;ste dag cykler Trine ud i skoven sammen med sin bedste veninde Rikke. De har ogs&#xE5; Rikkes hund Charlie med.  \nCharlie finder et spor. Og Rikke og Trine bliver n&#xF8;dt til at ringe efter politiet! \n\nLix 18.</MainDescription>\n    <ShortDescription>B&#xF8;rnekrimi for b&#xF8;rn p&#xE5; mellemtrinnet. Lix 18. Kriminovelle om hestepigen Trine.</ShortDescription>\n    <DigitalProtection>DigitalVandmaerkning</DigitalProtection>\n    <Price CurrencyCode="DKK">12</Price>\n    <Contributors>\n      <Contributor>\n        <Id>67804</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Lisa Rossavik</NamesBeforeKey>\n        <KeyNames>Rich</KeyNames>\n        <BiographicalNote/>\n        <Websites/>\n      </Contributor>\n      <Contributor>\n        <Id>67805</Id>\n        <ContributorRoleCode>B01</ContributorRoleCode>\n        <ContributorRoleName>Edited by</ContributorRoleName>\n        <NamesBeforeKey>Miriam Hummelsh&#xF8;j</NamesBeforeKey>\n        <KeyNames>Jakobsen</KeyNames>\n        <BiographicalNote/>\n        <Websites/>\n      </Contributor>\n    </Contributors>\n    <NumberOfPages>25 Sider</NumberOfPages>\n    <FileSize>171 KB</FileSize>\n    <FileVersion/>\n    <BookFormat>Reflowable</BookFormat>\n    <SubscriptionSaleAllowed>true</SubscriptionSaleAllowed>\n    <Authors>Lisa Rossavik Rich</Authors>\n    <EditedBy>Miriam Hummelsh&#xF8;j Jakobsen</EditedBy>\n    <SampleUrl>http://samples.pubhub.dk/9788793197428.epub</SampleUrl>\n    <RightList/>\n    <Subjects>\n      <SimpleSubject>\n        <Code>YF</Code>\n        <Description>B&#xF8;rn og unges fort&#xE6;llinger og sande historier</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>FF</Code>\n        <Description>Krimi og mysterier</Description>\n      </SimpleSubject>\n    </Subjects>\n    <Images>\n      <Image Type="Forside">https://images.pubhub.dk/originals/70693d78-d150-4fdc-950b-8ce1e6586857.jpeg</Image>\n      <Image Type="ForsideMiniature">https://images.pubhub.dk/thumbnails/70693d78-d150-4fdc-950b-8ce1e6586857.jpeg</Image>\n    </Images>\n    <PriceBeforeDiscount CurrencyCode="DKK">12</PriceBeforeDiscount>\n  </Book>\n</root>\n	9788793197428	2015-07-13 00:00:00+02	\N	\N	\N	\N	\N	\N	\N	3481437524
26255	eVa	PubHubMediaService	2015-07-14 04:13:30.607611+02	2015-07-14 04:27:41.862183+02	Ebog	epub	cea88d01-651d-43d1-9e3f-6e25793bf7d2	Pigeliv og kvindeliv	<?xml version="1.0"?>\n<root>\n  <Book xmlns="http://service.pubhub.dk/">\n    <BookType>Ebog</BookType>\n    <FileType>epub</FileType>\n    <BookId>cea88d01-651d-43d1-9e3f-6e25793bf7d2</BookId>\n    <Identifier>9788702163414</Identifier>\n    <IdentifierType>ISBN13</IdentifierType>\n    <Title>Pigeliv og kvindeliv</Title>\n    <Language>dan</Language>\n    <PublicationDate>03-08-2015</PublicationDate>\n    <Edition>Edition 1</Edition>\n    <ImprintName>Gyldendal</ImprintName>\n    <PublisherName>Gyldendal</PublisherName>\n    <MainDescription>&lt;p&gt;"Pigeliv &amp; kvindeliv" er en&#xA0;slags dannelsesroman om en ung outsider-pige, der s&#xF8;ger v&#xE6;k fra lillebysamfundet for gr&#xE5;digt at kaste sig ud i voksenlivet.&lt;/p&gt;\n\n&lt;p&gt;Del Jordan vokser op p&#xE5; faderens r&#xE6;vefarm for enden af Flats Road, men f&#xF8;lger senere med sin mor ind til byen Jubilee, hvor moren begynder at s&#xE6;lge leksika ved at g&#xE5; fra d&#xF8;r til d&#xF8;r. Det er is&#xE6;r den st&#xE6;rke og videbeg&#xE6;rlige mor, der pr&#xE6;ger Dels opv&#xE6;kst, og fylder hende med en blanding af skam over morens st&#xF8;vede hat, knurrende mave og ub&#xE6;ndige trang til at ytre sig, og med en viden om, at hun ikke er s&#xE5; forskellig fra sin mor, n&#xE5;r det kommer til stykket&lt;/p&gt;</MainDescription>\n    <DigitalProtection>DigitalVandmaerkning</DigitalProtection>\n    <Price CurrencyCode="DKK">55</Price>\n    <Contributors>\n      <Contributor>\n        <Id>67795</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Alice</NamesBeforeKey>\n        <KeyNames>Munro</KeyNames>\n        <Websites>\n          <Website>\n            <Id>d326f740-f14c-49d6-b2f4-c9f9cb83eb12</Id>\n            <WebsiteRole>7</WebsiteRole>\n            <WebsiteLink>http://www.gyldendal.dk/Alice-Munro</WebsiteLink>\n          </Website>\n        </Websites>\n      </Contributor>\n      <Contributor>\n        <Id>67796</Id>\n        <ContributorRoleCode>B06</ContributorRoleCode>\n        <ContributorRoleName>Translated by</ContributorRoleName>\n        <NamesBeforeKey>Lisbeth</NamesBeforeKey>\n        <KeyNames>M&#xF8;ller-Madsen</KeyNames>\n        <Websites/>\n      </Contributor>\n    </Contributors>\n    <FileSize>909 KB</FileSize>\n    <FileVersion>2.0</FileVersion>\n    <BookFormat>Reflowable</BookFormat>\n    <SubscriptionSaleAllowed>true</SubscriptionSaleAllowed>\n    <Authors>Alice Munro</Authors>\n    <TranslatedBy>Lisbeth M&#xF8;ller-Madsen</TranslatedBy>\n    <SampleUrl>http://samples.pubhub.dk/9788702163414.epub</SampleUrl>\n    <RightList/>\n    <Subjects>\n      <SimpleSubject>\n        <Code>FA</Code>\n        <Description>Moderne og samtidsfiktion (efter ca. 1945)</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>FYT</Code>\n        <Description>Sk&#xF8;nlitteratur i overs&#xE6;ttelse</Description>\n      </SimpleSubject>\n    </Subjects>\n    <Images>\n      <Image Type="ForsideMiniature">http://images.pubhub.dk/thumbnails/cea88d01-651d-43d1-9e3f-6e25793bf7d2.jpg</Image>\n      <Image Type="Forside">http://images.pubhub.dk/originals/cea88d01-651d-43d1-9e3f-6e25793bf7d2.jpg</Image>\n    </Images>\n    <PriceBeforeDiscount CurrencyCode="DKK">55</PriceBeforeDiscount>\n  </Book>\n</root>\n	9788702163414	2015-08-03 00:00:00+02	\N	\N	\N	\N	\N	\N	\N	3176320941
26253	eVa	PubHubMediaService	2015-07-14 04:12:59.934104+02	2015-07-14 04:27:55.23366+02	Ebog	epub	50026a9c-45c8-405f-8e77-6af2ed4fde00	The Ice People 9 - Without Roots	<?xml version="1.0"?>\n<root>\n  <Book xmlns="http://service.pubhub.dk/">\n    <BookType>Ebog</BookType>\n    <FileType>epub</FileType>\n    <BookId>50026a9c-45c8-405f-8e77-6af2ed4fde00</BookId>\n    <Identifier>9788771073577</Identifier>\n    <IdentifierType>ISBN13</IdentifierType>\n    <Title>The Ice People 9 - Without Roots</Title>\n    <Language>dan</Language>\n    <PublicationDate>13-07-2015</PublicationDate>\n    <Edition>e-bog udgave</Edition>\n    <PublisherName>Katrin Agency</PublisherName>\n    <PartNumber>9</PartNumber>\n    <NameOfBookSeries>The Legend of the Ice People</NameOfBookSeries>\n    <MainDescription>Mikael Lind of the Ice People is a lonely and deeply unhappy young man. Wars are being fought across Europe and although his foster parents want him to pursue a military career, Mikael harbors no military ambitions. Throughout his life, he was taught to give in to other people&#x2019;s decisions. This was also the case when he married a young woman whom he hardly knew.\n\nAnette is from the South of France and brought up as a strict Catholic. Her mother taught her that she must only comply with what her husband says until there are children in the marriage.\n\nBroken in body and mind, Mikael travels to Norway where he finds his roots and himself.\n\nThe story of the Ice People is a moving legend of love and supernatural powers, a tale of the essential struggle between good and evil.</MainDescription>\n    <DigitalProtection>DigitalVandmaerkning</DigitalProtection>\n    <Price CurrencyCode="DKK">63</Price>\n    <Contributors>\n      <Contributor>\n        <Id>67801</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Margit</NamesBeforeKey>\n        <KeyNames>Sandemo</KeyNames>\n        <BiographicalNote/>\n        <Websites/>\n      </Contributor>\n    </Contributors>\n    <NumberOfPages>290 Sider</NumberOfPages>\n    <FileSize>5424 KB</FileSize>\n    <FileVersion/>\n    <BookFormat>Reflowable</BookFormat>\n    <SubscriptionSaleAllowed>true</SubscriptionSaleAllowed>\n    <Authors>Margit Sandemo</Authors>\n    <SampleUrl>http://samples.pubhub.dk/9788771073577.epub</SampleUrl>\n    <RightList/>\n    <Subjects>\n      <SimpleSubject>\n        <Code>FM</Code>\n        <Description>Fantasy</Description>\n      </SimpleSubject>\n    </Subjects>\n    <Images>\n      <Image Type="ForsideMiniature">https://images.pubhub.dk/thumbnails/50026a9c-45c8-405f-8e77-6af2ed4fde00.jpg</Image>\n      <Image Type="Forside">https://images.pubhub.dk/originals/50026a9c-45c8-405f-8e77-6af2ed4fde00.jpg</Image>\n    </Images>\n    <PriceBeforeDiscount CurrencyCode="DKK">63</PriceBeforeDiscount>\n  </Book>\n</root>\n	9788771073577	2015-07-13 00:00:00+02	\N	\N	\N	\N	\N	\N	\N	3463808207
26252	eVa	PubHubMediaService	2015-07-14 04:12:45.659407+02	2015-07-14 04:27:59.490138+02	Ebog	epub	22352512-c59c-45f8-b080-4675e6560b56	Sæt en vagtpost ud	<?xml version="1.0"?>\n<root>\n  <Book xmlns="http://service.pubhub.dk/">\n    <BookType>Ebog</BookType>\n    <FileType>epub</FileType>\n    <BookId>22352512-c59c-45f8-b080-4675e6560b56</BookId>\n    <Identifier>9788711465264</Identifier>\n    <IdentifierType>GTIN13</IdentifierType>\n    <Title>S&#xE6;t en vagtpost ud</Title>\n    <Language>dan</Language>\n    <PublicationDate>14-07-2015</PublicationDate>\n    <ImprintName>Lindhardt og Ringhof</ImprintName>\n    <PublisherName>Lindhardt og Ringhof</PublisherName>\n    <MainDescription>Femoghalvtreds &#xE5;r efter at Harper Lees eneste roman og ud&#xF8;delige klassiker "Dr&#xE6;b ikke en sangfugl" udkom, kommer der nu endelig en toer. "S&#xE6;t en vagtpost ud" foreg&#xE5;r i midten af 1950&#x2019;erne og 20 &#xE5;r efter "Dr&#xE6;b ikke en sangfugl".  &lt;br&gt;&lt;br&gt;Den nu 26 &#xE5;r gamle Scout (Jean Louise Finch) er vendt hjem fra New York for at bes&#xF8;ge faren Atticus i den lille by Maycomb, Alabama, hvor hun voksede op. I en tid hvor racediskriminationen raser i USA, er Jean Louise tvunget til at k&#xE6;mpe med politiske s&#xE5;vel som personlige problemer, idet hun fors&#xF8;ger at forst&#xE5; sin fars holdning til samfundet og sine egne f&#xF8;lelser omkring det sted, hun er f&#xF8;dt og opvokset.&lt;br&gt;&lt;br&gt;"Dr&#xE6;b ikke en sangfugl" er solgt i over 40 millioner eksemplarer. Blev i 1961 tildelt Pulitzerprisen og er siden blevet en klassiker i amerikansk litteratur.</MainDescription>\n    <DigitalProtection>DigitalVandmaerkning</DigitalProtection>\n    <Price CurrencyCode="DKK">127.36</Price>\n    <RecommendedPrice CurrencyCode="DKK">199</RecommendedPrice>\n    <Contributors>\n      <Contributor>\n        <Id>67797</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Harper</NamesBeforeKey>\n        <KeyNames>Lee</KeyNames>\n        <Websites/>\n      </Contributor>\n      <Contributor>\n        <Id>67798</Id>\n        <ContributorRoleCode>B06</ContributorRoleCode>\n        <ContributorRoleName>Translated by</ContributorRoleName>\n        <NamesBeforeKey>Karen</NamesBeforeKey>\n        <KeyNames>Fastrup</KeyNames>\n        <Websites/>\n      </Contributor>\n    </Contributors>\n    <NumberOfPages>296 Sider</NumberOfPages>\n    <FileSize>2508 KB</FileSize>\n    <BookFormat>Reflowable</BookFormat>\n    <SubscriptionSaleAllowed>true</SubscriptionSaleAllowed>\n    <Authors>Harper Lee</Authors>\n    <TranslatedBy>Karen Fastrup</TranslatedBy>\n    <SampleUrl>http://samples.pubhub.dk/9788711465264.epub</SampleUrl>\n    <RightList/>\n    <Subjects>\n      <SimpleSubject>\n        <Code>1KBB</Code>\n        <Description>USA</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>1KBBSB</Code>\n        <Description>Alabama</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>FA</Code>\n        <Description>Moderne og samtidsfiktion (efter ca. 1945)</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>F</Code>\n        <Description>Fiktion og relaterede emner</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>3JJPG</Code>\n        <Description>ca. 1945 til ca. 1960</Description>\n      </SimpleSubject>\n    </Subjects>\n    <Images>\n      <Image Type="ForsideMiniature">http://images.pubhub.dk/thumbnails/22352512-c59c-45f8-b080-4675e6560b56.jpg</Image>\n      <Image Type="Forside">http://images.pubhub.dk/originals/22352512-c59c-45f8-b080-4675e6560b56.jpg</Image>\n    </Images>\n    <PriceBeforeDiscount CurrencyCode="DKK">127.36</PriceBeforeDiscount>\n  </Book>\n</root>\n	9788711465264	2015-07-14 00:00:00+02	\N	\N	\N	\N	\N	\N	\N	1578337102
26227	eVa	PubHubMediaService	2015-07-11 04:13:10.915106+02	2015-07-14 04:28:05.417905+02	Ebog	epub	3ac1566f-16a6-478c-9284-cce0af876d8f	Silas fanger et firspand	<?xml version="1.0"?>\n<root>\n  <Book xmlns="http://service.pubhub.dk/">\n    <BookType>Ebog</BookType>\n    <FileType>epub</FileType>\n    <BookId>3ac1566f-16a6-478c-9284-cce0af876d8f</BookId>\n    <Identifier>9788702178678</Identifier>\n    <IdentifierType>ISBN13</IdentifierType>\n    <Title>Silas fanger et firspand</Title>\n    <PublicationDate>14-08-2015</PublicationDate>\n    <Edition>Edition 1</Edition>\n    <ImprintName>Gyldendal</ImprintName>\n    <PublisherName>Gyldendal</PublisherName>\n    <NameOfBookSeries>Silas</NameOfBookSeries>\n    <MainDescription>&lt;p&gt;Den f&#xF8;rste bog i serien om den frie og selvst&#xE6;ndige dreng Silas blev Cecil B&#xF8;dkers debut som b&#xF8;rnebogsforfatter tilbage i 1967, og serien har lige siden betaget unge s&#xE5;vel som voksne l&#xE6;sere. I dag anses Silas-figuren for en af de mest opsigtsv&#xE6;kkende figurer i dansk b&#xF8;rnelitteratur. Bogen er med i Kulturministeriets kanon for b&#xF8;rnekultur fra 2006.&lt;/p&gt;\n\n&lt;p&gt;&#x2019;Silas fanger et firspand&#x2019; er 3. bind i serien om drengen Silas, og her kan du h&#xF8;re, hvordan Silas sammen med en fornem k&#xF8;bmandss&#xF8;n drager p&#xE5; eventyr. Her m&#xF8;der de bl.a. Hestekragen igen, f&#xF8;r Silas vender hjem til Ben-Godik.&lt;/p&gt;\n\n&lt;p&gt;Pressen skrev om Cecil B&#xF8;dkers serie om Silas:&lt;/p&gt;\n\n&lt;p&gt;"Silas-serien er en kraftpr&#xE6;station og kvalitativt en imponerende milep&#xE6;l i dansk b&#xF8;rnelitteraturhistorie." - Ulrik T. Skafte, Jyllands-Posten&lt;/p&gt;\n\n&lt;p&gt;"Der kan ikke aftvinges mindre end total respekt for en stor serie ... Der er en stor indre alvor i historien om Silas' liv. Det er virkeligt og fuld af handling og valg. Derfor vil han ride mange &#xE5;r endnu." - Steffen Larsen, Aktuelt&lt;/p&gt;</MainDescription>\n    <DigitalProtection>DigitalVandmaerkning</DigitalProtection>\n    <Price CurrencyCode="DKK">60</Price>\n    <Contributors>\n      <Contributor>\n        <Id>67683</Id>\n        <ContributorRoleCode>A01</ContributorRoleCode>\n        <ContributorRoleName>By (author)</ContributorRoleName>\n        <NamesBeforeKey>Cecil</NamesBeforeKey>\n        <KeyNames>B&#xF8;dker</KeyNames>\n        <Websites>\n          <Website>\n            <Id>303fb998-98b3-4924-a6aa-4d1aa1b5aac5</Id>\n            <WebsiteRole>7</WebsiteRole>\n            <WebsiteLink>http://www.gyldendal.dk/Cecil-B%c3%b8dker</WebsiteLink>\n          </Website>\n        </Websites>\n      </Contributor>\n    </Contributors>\n    <NumberOfPages>268 Sider</NumberOfPages>\n    <FileSize>415 KB</FileSize>\n    <FileVersion>2.0</FileVersion>\n    <BookFormat>Reflowable</BookFormat>\n    <SubscriptionSaleAllowed>true</SubscriptionSaleAllowed>\n    <Authors>Cecil B&#xF8;dker</Authors>\n    <SampleUrl>http://samples.pubhub.dk/9788702178678.epub</SampleUrl>\n    <RightList/>\n    <Subjects>\n      <SimpleSubject>\n        <Code>5AL</Code>\n        <Description>L&#xE6;sealder fra ca. 10 &#xE5;r</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>YFC</Code>\n        <Description>Eventyrsfort&#xE6;llinger (b&#xF8;rn og unge)</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>YFB</Code>\n        <Description>Sk&#xF8;nlitteratur (b&#xF8;rn og unge)</Description>\n      </SimpleSubject>\n      <SimpleSubject>\n        <Code>5AJ</Code>\n        <Description>L&#xE6;sealder fra ca. 8 &#xE5;r</Description>\n      </SimpleSubject>\n    </Subjects>\n    <Images>\n      <Image Type="ForsideMiniature">http://images.pubhub.dk/thumbnails/3ac1566f-16a6-478c-9284-cce0af876d8f.jpg</Image>\n      <Image Type="Forside">http://images.pubhub.dk/originals/3ac1566f-16a6-478c-9284-cce0af876d8f.jpg</Image>\n    </Images>\n    <PriceBeforeDiscount CurrencyCode="DKK">60</PriceBeforeDiscount>\n  </Book>\n</root>\n	9788702178678	2015-08-14 00:00:00+02	\N	\N	\N	\N	\N	pbh	2015-07-13 14:33:10.40439+02	4282886439
\.


--
-- Data for Name: mediaservicedref; Type: TABLE DATA; Schema: public; Owner: -
--

COPY mediaservicedref (seqno, createdate, base, lokalid, bibliotek, type, matchtype) FROM stdin;
26262	\N	Basis	0 177 147 7	870970	title	100
26255	\N	Basis	0 802 978 4	870970	title	204
26255	\N	Basis	0 644 966 2	870970	title	204
26255	\N	Basis	0 867 570 8	870970	title	200
26255	\N	Basis	0 867 466 3	870970	title	200
26241	\N	Basis	5 184 349 5	870970	isbn13	119
26227	\N	Basis	2 848 768 1	870970	title	320
26227	\N	Basis	0 697 847 9	870970	title	204
26227	\N	Basis	0 633 835 6	870970	title	300
26227	\N	Basis	0 461 253 1	870970	title	204
26227	\N	Basis	0 389 986 1	870970	title	304
26227	\N	Basis	0 290 060 2	870970	title	204
26227	\N	Basis	0 111 340 2	870970	title	204
26227	\N	Basis	2 241 344 9	870970	title	304
\.


--
-- Data for Name: mediaservicenote; Type: TABLE DATA; Schema: public; Owner: -
--

COPY mediaservicenote (seqno, createdate, updated, type, text, initials, status) FROM stdin;
\.


--
-- Name: mediaservicdnote_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY mediaservicenote
    ADD CONSTRAINT mediaservicdnote_pkey PRIMARY KEY (seqno, text);


--
-- Name: mediaservice_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY mediaservice
    ADD CONSTRAINT mediaservice_pkey PRIMARY KEY (seqno);


--
-- Name: bookid_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bookid_idx ON mediaservice USING btree (bookid);


--
-- Name: providerisbn13_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX providerisbn13_idx ON mediaservice USING btree (provider, isbn13);


--
-- PostgreSQL database dump complete
--

