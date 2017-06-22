<?php

//include '../../inc/phpunit';

class Example extends PHPUnit_Extensions_SeleniumTestCase {

    public static $browsers = array(
        array(
            'name' => 'Firefox on Windows',
            'browser' => 'firefox',
            'host' => 'selw7-01.dbc.dk',
            'port' => 4444,
            'timeout' => 30000
        )
    );

    function login($login) {
        $this->open("/~hhl/posthus/eVALU/");
        $this->click("css=ul.right > li.active > button");
        $this->setSpeed("3000");
        $this->click("link=×");
        $this->setSpeed("0");
    }

    function StatusTest($sta) {
        $i = 2;
        foreach ($sta as $status) {
            $res = $this->verifyText("//tr[$i]/td[2]", $status);
            $i++;
        }
    }

    protected function setUp() {
//        $this->setBrowser("*firefox");

        $this->setBrowserUrl("http://guesstimate.dbc.dk");
        $cmd = "ls -ltr";
        $res = exec($cmd, $output);
        print_r($output);

//        $this->open("/~hhl/posthus/eVALU/");
//        $this->click("css=ul.right > li.active > button");
//        $this->setSpeed("3000");
//        $this->click("link=×");
//        $this->setSpeed("0");
    }

    public function testLogin() {

        $this->login('admin');

        $this->click("link=Vis lister");
        $this->click("link=Alle");
        $this->waitForPageToLoad("30000");
        $sta = array('eVa', 'eVa', 'eVa', 'eVa', 'eVa', 'eVa', 'eVa', 'eVa', 'eVa');
        $this->StatusTest($sta);

        // test om Fortryd virker
        $this->click("link=Vis lister");
        $this->click("link=Alle");
        $this->waitForPageToLoad("30000");
        $this->click("link=Silas fanger et firspand");
        $this->waitForPageToLoad("30000");
        $this->click("css=button[name=\"cmd\"]");
        $this->waitForPageToLoad("30000");
        $this->click("link=Vis lister");
        $this->click("link=Alle");
        $this->waitForPageToLoad("30000");
        $this->StatusTest($sta);
    }

}

?>