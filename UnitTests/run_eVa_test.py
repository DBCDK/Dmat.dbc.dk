# -*- coding: utf-8 -*-

# /**
#  *
#  * This file is part of Open Library System.
#  * Copyright © 2009, Dansk Bibliotekscenter a/s,
#  * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
#  *
#  * Open Library System is free software: you can redistribute it and/or modify
#  * it under the terms of the GNU Affero General Public License as published by
#  * the Free Software Foundation, either version 3 of the License, or
#  * (at your option) any later version.
#  *
#  * Open Library System is distributed in the hope that it will be useful,
#  * but WITHOUT ANY WARRANTY; without even the implied warranty of
#  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  * GNU Affero General Public License for more details.
#  *
#  * You should have received a copy of the GNU Affero General Public License
#  * along with Open Library System.  If not, see <http://www.gnu.org/licenses/>.
#  */

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import Select
from selenium.common.exceptions import NoSuchElementException
from selenium.common.exceptions import NoAlertPresentException
from xmlrunner import XMLTestRunner
import xmlrunner

import unittest, time, re, sys


# from time import sleep

class eVaTest(unittest.TestCase):
    WHERE = 'local'
    # PLATFORM = 'WINDOWS 10'
    # BROWSER = 'firefox'
    SAUCE_USERNAME = 'empty'
    SAUCE_KEY = 'empty'
    BASEURL = 'localhost'

    def setUp(self):
        desired_caps = {}
        # desired_caps['platform'] = self.PLATFORM
        # desired_caps['browserName'] = self.BROWSER
        desired_caps = {'browserName': "firefox"}
        desired_caps['screenResolution'] = "1920x1080"
        desired_caps['platform'] = "Windows 10"
        # desired_caps['version'] = "46.0"
        sauce_string = self.SAUCE_USERNAME + ':' + self.SAUCE_KEY
        if self.WHERE == 'remote':
            self.driver = webdriver.Remote('http://' + sauce_string + '@ondemand.saucelabs.com:80/wd/hub', desired_caps)
        else:
            self.driver = webdriver.Firefox()
            self.driver.implicitly_wait(30)
            # self.base_url = "http://devel7.dbc.dk/"
            self.base_url = self.BASEURL
            self.verificationErrors = []
            self.accept_next_alert = True
            self.driver.maximize_window()
            self.driver.set_window_size("1920", "1080")

    def waitforfinido(self):
        driver = self.driver
        for i in range(60):
            try:
                if "finido" == driver.find_element_by_id("finido").text: break
            except:
                pass
            time.sleep(1)
        else:
            self.fail("time out")

    def test_EVA(self):
        driver = self.driver
        driver.get(self.base_url + "/eVALU/index.php?test=true")
        driver.find_element_by_link_text("Test-apps").click()
        driver.find_element_by_id("restore_match").click()
        driver.get(self.base_url + "/eVALU/login.php")
        driver.find_element_by_name("initials").clear()
        driver.find_element_by_name("initials").send_keys("eVa")
        driver.find_element_by_name("passwd").clear()
        driver.find_element_by_name("passwd").send_keys("eVa")
        driver.find_element_by_name("login").click()
        driver.get(self.base_url + "/eVALU/index.php?test=true")

        # 7 test for "indsæt faust" og "ingen af nedenstående"
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eVa").click()
        driver.find_element_by_link_text(u"»").click()
        driver.find_element_by_link_text("En verdensomsejling under havet").click()
        driver.find_element_by_id("faust").clear()
        driver.find_element_by_id("faust").send_keys("2 222 224 2")
        driver.find_element_by_name("ind").click()
        self.assertEqual("2 222 224 2 (:351)", driver.find_element_by_xpath(
            "//button[@onclick=\"openCloseWin('open', 'seqno=7&base=&lokalid=2 222 224 2&bibliotek=870970&type=manuelt&matchtype=351')\"]").text)
        driver.find_element_by_xpath("(//button[@id='waiting'])[2]").click()
        driver.find_element_by_id("S").click()
        driver.find_element_by_id("OK").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("7")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:7", driver.find_element_by_id("seqno7").text)
        self.assertEqual("eLu", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM S", driver.find_element_by_id("choice").text)

        # 8
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("8")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("c_title1").click()
        driver.find_element_by_id("BKMV").click()
        driver.find_element_by_id("newRegistration").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("8")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:8", driver.find_element_by_id("seqno8").text)
        self.assertEqual("eLu", driver.find_element_by_id("status").text)
        self.assertEqual("BKMV", driver.find_element_by_id("choice").text)
        # self.assertEqual("UpdatePromat", driver.find_element_by_id("status").text)

        # 9
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("9")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("DBF").click()
        driver.find_element_by_id("OK").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("9")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:9", driver.find_element_by_id("seqno9").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBF", driver.find_element_by_id("choice").text)

        # 10
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("10")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("drop").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("10")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:10", driver.find_element_by_id("seqno10").text)
        self.assertEqual("Drop", driver.find_element_by_id("status").text)

        # 11
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("11")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("waiting").click()
        driver.find_element_by_id("notetext").clear()
        driver.find_element_by_id("notetext").send_keys("test af afventer")
        driver.find_element_by_id("wait").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("11")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:11", driver.find_element_by_id("seqno11").text)
        self.assertEqual("Afventer", driver.find_element_by_id("status").text)

        # 12 no test

        # 13
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("13")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("c_ti_au1").click()
        driver.find_element_by_id("V").click()
        driver.find_element_by_id("IsRegistred").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("13")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:13", driver.find_element_by_id("seqno13").text)
        self.assertEqual("UpdatePublizon", driver.find_element_by_id("status").text)
        self.assertEqual("DBF", driver.find_element_by_id("choice").text)

        # 14
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("14")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("c_ti_au1").click()
        driver.find_element_by_id("V").click()
        driver.find_element_by_id("S").click()
        driver.find_element_by_id("BKM").click()
        driver.find_element_by_id("DBF").click()
        driver.find_element_by_id("newRegistration").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("14")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:14", driver.find_element_by_id("seqno14").text)
        self.assertEqual("Template", driver.find_element_by_id("status").text)
        self.assertEqual("DBF", driver.find_element_by_id("choice").text)

        # 15
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("15")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("c_ti_au1").click()
        driver.find_element_by_id("V").click()
        driver.find_element_by_id("DBF").click()
        driver.find_element_by_id("newRegistration").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("15")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:15", driver.find_element_by_id("seqno15").text)
        self.assertEqual("eLu", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V", driver.find_element_by_id("choice").text)

        # 16
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("16")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("c_ti_au1").click()
        driver.find_element_by_id("newRegistration").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("16")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:16", driver.find_element_by_id("seqno16").text)
        self.assertEqual("eLu", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V", driver.find_element_by_id("choice").text)

        # 17
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("17")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("c_ti_au1").click()
        driver.find_element_by_id("newRegistration").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("17")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:17", driver.find_element_by_id("seqno17").text)
        self.assertEqual("eLu", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V", driver.find_element_by_id("choice").text)
        self.assertEqual(">>", driver.find_element_by_xpath(
            "//button[@onclick=\"openCloseWin('insertLink', 'seqno=17&lokalid=2 222 229 2&bibliotek=870970&base=Basis', 'eLu')\"]").text)

        # test seqno 18
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("18")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("V").click()
        driver.find_element_by_id("OK").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("18")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:18", driver.find_element_by_id("seqno18").text)
        self.assertEqual("eLu", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V", driver.find_element_by_id("choice").text)

        # test seqno 19
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("19")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("c_ti_au1").click()
        driver.find_element_by_id("DBF").click()
        driver.find_element_by_id("newRegistration").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("19")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:19", driver.find_element_by_id("seqno19").text)
        self.assertEqual("eLu", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V B S", driver.find_element_by_id("choice").text)

        # test seqno 21
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("21")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        # driver.find_element_by_id("c_ti_au1").click()
        # driver.find_element_by_id("newRegistration").click()
        driver.find_element_by_xpath("(//button[@id='waiting'])[2]").click()
        driver.find_element_by_id("V").click()
        driver.find_element_by_id("OK").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("21")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:21", driver.find_element_by_id("seqno21").text)
        self.assertEqual("eLu", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V", driver.find_element_by_id("choice").text)

        # test seqno 22
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("22")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("c_ti_au1").click()
        # driver.find_element_by_id("V").click()
        driver.find_element_by_id("newRegistration").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("22")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:22", driver.find_element_by_id("seqno22").text)
        self.assertEqual("eLu", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V", driver.find_element_by_id("choice").text)

        # test seqno 23
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("23")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        driver.find_element_by_id("V").click()
        driver.find_element_by_id("OK").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("23")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:23", driver.find_element_by_id("seqno23").text)
        self.assertEqual("eLu", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V", driver.find_element_by_id("choice").text)

        # test seqno 24
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("24")
        driver.find_element_by_xpath("//input[@value='Seqno']").click()
        driver.find_element_by_id("c_ti_au1").click()
        self.assertEqual("on", driver.find_element_by_id("DBR").get_attribute("value"))
        aa = driver.find_element_by_id("DBF").get_attribute("value")
        # self.assertEqual("off", driver.find_element_by_id("DBF").get_attribute("value"))
        driver.find_element_by_id("DBF").click()
        self.assertEqual("on", driver.find_element_by_id("DBF").get_attribute("value"))
        # self.assertEqual("off", driver.find_element_by_id("DBR").get_attribute("value"))
        driver.find_element_by_id("DBR").click()
        # self.assertEqual("off", driver.find_element_by_id("DBF").get_attribute("value"))
        driver.find_element_by_id("newRegistration").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("24")
        driver.find_element_by_xpath("//input[@value='Seqno']").click()
        self.assertEqual("seqno:24", driver.find_element_by_id("seqno24").text)
        self.assertEqual("eLu", driver.find_element_by_id("status").text)
        self.assertEqual("DBR BKM V", driver.find_element_by_id("choice").text)

        # test seqno 25
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("25")
        driver.find_element_by_xpath("//input[@value='Seqno']").click()
        driver.find_element_by_id("DBR").click()
        driver.find_element_by_id("V").click()
        driver.find_element_by_id("OK").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("25")
        driver.find_element_by_xpath("//input[@value='Seqno']").click()
        self.assertEqual("seqno:25", driver.find_element_by_id("seqno25").text)
        self.assertEqual("eLu", driver.find_element_by_id("status").text)
        self.assertEqual("DBR BKM V", driver.find_element_by_id("choice").text)

        # test seqno 26
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("26")
        driver.find_element_by_xpath("//input[@value='Seqno']").click()
        driver.find_element_by_id("c_ti_au_pu1").click()
        # ERROR: Caught exception [ERROR: Unsupported command [waitForPopUp | _self | 30000]]
        driver.find_element_by_id("V").click()
        driver.find_element_by_id("BKM").click()
        driver.find_element_by_id("newRegistration").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("26")
        driver.find_element_by_xpath("//input[@value='Seqno']").click()
        self.assertEqual("seqno:26", driver.find_element_by_id("seqno26").text)
        self.assertEqual("Template", driver.find_element_by_id("status").text)
        self.assertEqual("DBR", driver.find_element_by_id("choice").text)

        # test seqno 27
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("27")
        driver.find_element_by_xpath("//input[@value='Seqno']").click()
        driver.find_element_by_id("DBR").click()
        driver.find_element_by_id("OK").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("27")
        driver.find_element_by_xpath("//input[@value='Seqno']").click()
        self.assertEqual("seqno:27", driver.find_element_by_id("seqno27").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBR", driver.find_element_by_id("choice").text)

        driver.get(self.base_url + "/eVALU/eVa.php?seqno=&cmd=Oversigt")

        # make a backup of the current state:
        # driver.get(self.base_url + "/~hhl/posthus/eVALU/index.php")
        driver.find_element_by_link_text("Test-apps").click()
        driver.find_element_by_id("backup_eVa").click()

    def is_element_present(self, how, what):
        try:
            self.driver.find_element(by=how, value=what)
        except NoSuchElementException as e:
            return False
        return True

    def is_alert_present(self):
        try:
            self.driver.switch_to.alert()
        except NoAlertPresentException as e:
            return False
        return True

    def close_alert_and_get_its_text(self):
        try:
            alert = self.driver.switch_to.alert()
            alert_text = alert.text
            if self.accept_next_alert:
                alert.accept()
            else:
                alert.dismiss()
            return alert_text
        finally:
            self.accept_next_alert = True

    def tearDown(self):
        self.driver.quit()
        self.assertEqual([], self.verificationErrors)


if __name__ == "__main__":
    if len(sys.argv) > 1:
        # eVaTest.PORT = sys.argv.pop()
        eVaTest.BASEURL = sys.argv.pop()
        eVaTest.SAUCE_KEY = sys.argv.pop()
        eVaTest.SAUCE_USERNAME = sys.argv.pop()
        eVaTest.WHERE = sys.argv.pop()
        # print "SAUCE_USERNAME is (" + eVaTest.SAUCE_USERNAME + ")"
        # print "SAUCE_KEY is (" + eVaTest.SAUCE_KEY + ")"
        # print "WHERE is (" + eVaTest.WHERE + ")"
    # loader = unittest.TestLoader()
    # tests = loader.discover()
    # runner = XMLTestRunner()
    unittest.main(
        testRunner=xmlrunner.XMLTestRunner(output='test-reports'),
        failfast=False, buffer=False, catchbreak=False
    )
    # runner.run(tests)
    # xmlrunner.XMLTestRunner(verbosity=2,output='fil.xml').run(tests)
