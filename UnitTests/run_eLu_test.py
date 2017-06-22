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
# from selenium.webdriver.common.by import By
# from selenium.webdriver.common.keys import Keys
# from selenium.webdriver.support.ui import Select
from selenium.common.exceptions import NoSuchElementException
from selenium.common.exceptions import NoAlertPresentException
import xmlrunner
import unittest, time, re, sys


class eLuTest(unittest.TestCase):
    WHERE = 'local'
    PLATFORM = 'WINDOWS 7'
    BROWSER = 'firefox'
    SAUCE_USERNAME = 'empty'
    SAUCE_KEY = 'empty'

    def setUp(self):
        desired_caps = {}
        desired_caps['platform'] = self.PLATFORM
        desired_caps['browserName'] = self.BROWSER
        desired_caps = {'browserName': "firefox"}
        # desired_caps = {'browserName': "EI"}
        desired_caps['platform'] = "Windows 10"
        desired_caps['version'] = "46"
        desired_caps['screenResolution'] = "1920x1080"
        sauce_string = self.SAUCE_USERNAME + ':' + self.SAUCE_KEY
        if self.WHERE == 'remote':
            self.driver = webdriver.Remote('http://' + sauce_string + '@ondemand.saucelabs.com:80/wd/hub', desired_caps)
        else:
            self.driver = webdriver.Firefox()
            self.driver.implicitly_wait(30)
            self.base_url = self.BASEURL
            # self.base_url = "http://devel7.dbc.dk/~hhl/posthus/eVALU/"
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

    def test_ELU(self):
        driver = self.driver
        driver.get(self.base_url + "/eVALU/index.php?test=true")

        driver.get(self.base_url + "/eVALU/login.php")
        driver.find_element_by_name("initials").clear()
        driver.find_element_by_name("initials").send_keys("eLu")
        driver.find_element_by_name("passwd").clear()
        driver.find_element_by_name("passwd").send_keys("eLu")
        driver.find_element_by_name("login").click()

        driver.find_element_by_link_text("Oversigt").click()
        time.sleep(1)
        # driver.find_element_by_link_text("Test-apps").click()
        driver.find_element_by_id("testapps").click()
        driver.find_element_by_id("restore_eVa").click()
        time.sleep(2)
        driver.find_element_by_link_text("Oversigt").click()
        time.sleep(1)

        # 7 en post der ikke skal have lektørudt.
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eLu").click()
        driver.find_element_by_link_text(u"»").click()
        driver.find_element_by_link_text("En verdensomsejling under havet").click()
        # for i in range(60):
        #     try:
        #         if "OK" == driver.find_element_by_id("LekOk").text: break
        #     except:
        #         pass
        #     time.sleep(1)
        # else:
        #     self.fail("time out")
        driver.find_element_by_id("LekOk").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("7")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:7", driver.find_element_by_id("seqno7").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM S", driver.find_element_by_id("choice").text)

        # 8 En post med BKMV - skal til UpdateBasis og promat skal være sat i basen.
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eLu").click()
        driver.find_element_by_link_text(u"»").click()
        driver.find_element_by_link_text("Polli 4 - Med vinden til havet").click()
        driver.find_element_by_id("LekOk").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("8")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:8", driver.find_element_by_id("seqno8").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("BKMV", driver.find_element_by_id("choice").text)

        # 15
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eLu").click()
        driver.find_element_by_link_text(u"Kvindens fortælling").click()
        # først: forkert isbn
        driver.find_element_by_id("expisbn").clear()
        driver.find_element_by_id("expisbn").send_keys("9801111111114")
        driver.find_element_by_id("LekOk").click()
        self.assertEqual("Forkert ISBN!", driver.find_element_by_css_selector("label.errortxt").text)
        # Rigtigt isbn - vælg trykt som lektør
        driver.find_element_by_id("expisbn").clear()
        driver.find_element_by_id("expisbn").send_keys("9803333333330")
        driver.find_element_by_id("LekOk").click()
        # driver.find_element_by_id("L").click()
        aa = driver.find_element_by_id("L").is_enabled();
        self.assertFalse(aa);

        # fortryd trykt vælg istedet ebog
        driver.find_element_by_id("Regret").click()
        driver.find_element_by_id("expisbn").clear()
        driver.find_element_by_id("expisbn").send_keys("9803333333330")
        driver.find_element_by_id("L").click()
        driver.find_element_by_id("LekOk").click()
        # self.assertEqual(u"Du har valgt at der skal laves lektørudtalelse på e-bogen.\n Der vil blive indsat et link, f07, i ACC posten for den trykte bog (9801111111118).", driver.find_element_by_xpath("//div[10]/div").text)
        driver.find_element_by_id("OkLek").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("15")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:15", driver.find_element_by_id("seqno15").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V L", driver.find_element_by_id("choice").text)

        # 16 Vælg 'L' og ISBN - der skal dannes 2 acc, e-bog uden lektør trykt med
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eLu").click()
        driver.find_element_by_link_text(u"Litteratur i bevægelse").click()
        driver.find_element_by_id("expisbn").clear()
        driver.find_element_by_id("expisbn").send_keys("9803333333330")
        driver.find_element_by_id("LekOk").click()
        self.assertEqual("ISBN findes allerede i Dmat (seqno:15)",
                         driver.find_element_by_css_selector("label.errortxt").text)
        driver.find_element_by_id("expisbn").clear()
        driver.find_element_by_id("expisbn").send_keys("9802222222229")
        driver.find_element_by_id("LekOk").click()
        driver.find_element_by_id("OkLek").click()

        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("16")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:16", driver.find_element_by_id("seqno16").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V", driver.find_element_by_id("choice").text)

        # 17 vælg en post fra basis der har lektørudtalelse.
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eLu").click()
        driver.find_element_by_link_text(u"Struensee - Til nytte og fornøjelse").click()
        driver.find_element_by_xpath(
            "//button[@onclick=\"openCloseWin('insertLink', 'seqno=17&lokalid=2 222 229 2&bibliotek=870970&base=Basis', 'eLu')\"]").click()
        driver.find_element_by_id("LekOk").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("17")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:17", driver.find_element_by_id("seqno17").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V", driver.find_element_by_id("choice").text)

        # 18
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eLu").click()
        driver.find_element_by_link_text("Nemesis").click()
        driver.find_element_by_id("L").click()
        driver.find_element_by_name("Lek").click()
        # self.assertEqual(
        #     u"Du har valgt at der skal laves lektørudtalelse på e-bogen.\n Der vil blive indsat et link, f07, i ACC posten for den trykte bog (9801111111118).",
        #     driver.find_element_by_xpath("//div[10]/div").text)
        try:
            self.assertEqual("9801111111118", driver.find_element_by_id("expisbn").get_attribute("value"))
        except AssertionError as e:
            self.verificationErrors.append(str(e))
        driver.find_element_by_xpath("(//button[@name='cmd'])[6]").click()
        try:
            self.assertEqual("9801111111118", driver.find_element_by_id("expisbn").get_attribute("value"))
        except AssertionError as e:
            self.verificationErrors.append(str(e))
        driver.find_element_by_id("L").click()
        driver.find_element_by_name("Lek").click()
        # self.assertEqual(
        #     u"Du har valgt at der skal laves lektørudtalelse på e-bogen.\n Der vil blive indsat et link, f07, i ACC posten for den trykte bog (9801111111118).",
        #     driver.find_element_by_xpath("//div[10]/div").text)
        driver.find_element_by_xpath("(//button[@name='cmd'])[5]").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("18")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:18", driver.find_element_by_id("seqno18").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V L", driver.find_element_by_id("choice").text)

        # 19
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eLu").click()
        driver.find_element_by_link_text("Dystopia").click()
        driver.find_element_by_id("L").click()
        driver.find_element_by_id("LekOk").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("19")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:19", driver.find_element_by_id("seqno19").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V B S L", driver.find_element_by_id("choice").text)

        # 21
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eLu").click()
        driver.find_element_by_link_text(u"Skydykker uden faldskærm").click()
        driver.find_element_by_xpath(
            "//button[@onclick=\"openCloseWin('insertLink', 'seqno=21&lokalid=2 222 232 2&bibliotek=870970&base=Basis', 'eLu')\"]").click()
        driver.find_element_by_id("LekOk").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("21")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:21", driver.find_element_by_id("seqno21").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V", driver.find_element_by_id("choice").text)

        # 22
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eLu").click()
        driver.find_element_by_link_text("Talent").click()
        driver.find_element_by_id("L").click()
        driver.find_element_by_id("LekOk").click()
        # driver.find_element_by_id("expisbn").clear()
        # driver.find_element_by_id("expisbn").send_keys("")
        # driver.find_element_by_id("LekOk").click()

        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("22")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:22", driver.find_element_by_id("seqno22").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V L", driver.find_element_by_id("choice").text)

        # 23
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eLu").click()
        driver.find_element_by_link_text("Nissemarkedet og andre digte").click()
        driver.find_element_by_id("expisbn").clear()
        driver.find_element_by_id("expisbn").send_keys("9804444444441")
        driver.find_element_by_id("LekOk").click()
        driver.find_element_by_id("OkLek").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("23")
        driver.find_element_by_xpath(u"//input[@value='Seqno']").click()
        self.assertEqual("seqno:23", driver.find_element_by_id("seqno23").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBF BKM V", driver.find_element_by_id("choice").text)

        # 24
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eLu").click()
        driver.find_element_by_link_text(u"Frøken Jensens Kogebog").click()
        driver.find_element_by_id("LekOk").click()
        driver.find_element_by_id("Regret").click()
        driver.find_element_by_id("expisbn").clear()
        driver.find_element_by_id("expisbn").send_keys("")
        driver.find_element_by_id("LekOk").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("24")
        driver.find_element_by_xpath("//input[@value='Seqno']").click()
        self.assertEqual("seqno:24", driver.find_element_by_id("seqno24").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBR BKM V", driver.find_element_by_id("choice").text)

        # 25
        driver.find_element_by_link_text("Vis lister").click()
        driver.find_element_by_link_text("eLu").click()
        driver.find_element_by_link_text("Tredive pund guld").click()
        driver.find_element_by_id("L").click()
        driver.find_element_by_id("LekOk").click()
        driver.find_element_by_name("seqnodirect").clear()
        driver.find_element_by_name("seqnodirect").send_keys("25")
        driver.find_element_by_xpath("//input[@value='Seqno']").click()
        self.assertEqual("seqno:25", driver.find_element_by_id("seqno25").text)
        self.assertEqual("UpdateBasis", driver.find_element_by_id("status").text)
        self.assertEqual("DBR BKM V L", driver.find_element_by_id("choice").text)

        # make a copy of the tables;
        driver.find_element_by_link_text("Oversigt").click()
        driver.find_element_by_link_text("Test-apps").click()
        driver.find_element_by_id("backup_eLu").click()

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
        eLuTest.BASEURL = sys.argv.pop()
        eLuTest.SAUCE_KEY = sys.argv.pop()
        eLuTest.SAUCE_USERNAME = sys.argv.pop()
        eLuTest.WHERE = sys.argv.pop()
        # print "SAUCE_USERNAME is (" + eLuTest.SAUCE_USERNAME + ")"
        # print "SAUCE_KEY is (" + eLuTest.SAUCE_KEY + ")"
        # print "WHERE is (" + eLuTest.WHERE + ")"
    unittest.main(
        testRunner=xmlrunner.XMLTestRunner(output='test-reports'),
        failfast=False, buffer=False, catchbreak=False
    )
    # unittest.main(verbosity=2)
