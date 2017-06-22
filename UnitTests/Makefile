# ============================================================================
#			Commands
# ============================================================================

CC=php -l

# ============================================================================
#			Objects
# ============================================================================

SOURCES=$(patsubst %.php,%.chk,$(wildcard *.php))

# ============================================================================
#			Targets
# ============================================================================

all: doxygen install compile

compile:
	php Check_syntax.php

%.chk: %.php
	$(CC) $<



doxygen:
	doxygen posthus.doxygen

install:
	svn co https://svn.dbc.dk/repos/php/Projects/posthusExe/DigitalResources DigitalResources
	svn co https://svn.dbc.dk/repos/php/Projects/posthusExe/paf paf
	svn co https://svn.dbc.dk/repos/php/Projects/posthusExe/UnitTests UnitTests
	svn co https://svn.dbc.dk/repos/php/Projects/posthusExe/venv venv
	#virtualenv venv
	#. venv/bin/activate
	#pip install unittest-xml-reporting
	#pip install -U selenium
	#pip install xmlrunner
	rm -rf Dmat_INSTALL
	git clone https://git.dbc.dk/dmat/Dmat_INSTALL
	php makeInstall.php -v globalVars.php,privat_USER.php
