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
# from xmlrunner import XMLTestRunner
import xmlrunner

import unittest, time, re, sys, os
# from time import sleep

class sovsTest(unittest.TestCase):

    WHERE = 'local'
    PLATFORM = 'WINDOWS 7'
    BROWSER = 'firefox'
    SAUCE_USERNAME = 'empty'
    SAUCE_KEY = 'empty'

    def setUp(self):
        desired_caps = {}
        desired_caps['platform'] = self.PLATFORM
        desired_caps['browserName'] = self.BROWSER
        # desired_caps = {'browserName': "firefox"}
        # desired_caps['platform'] = "Windows 7"
        desired_caps['version'] = "44.0"
        sauce_string = self.SAUCE_USERNAME + ':' + self.SAUCE_KEY
        if self.WHERE == 'remote':
            # print "remote"
            self.driver = webdriver.Remote('http://' + sauce_string + '@ondemand.saucelabs.com:80/wd/hub', desired_caps)
            from sauceclient import SauceClient
            self.sauce_client = SauceClient(self.SAUCE_USERNAME, self.SAUCE_KEY)

        else:
            # print "local"
            self.driver = webdriver.Firefox()
        self.driver.implicitly_wait(30)
        self.base_url = "http://devel7.dbc.dk/"
        self.verificationErrors = []
        self.accept_next_alert = True
        self.driver.maximize_window()

        sessionID = self.driver.session_id
        sessionName = 'test_sovs'
        print ("SauceOnDemandSessionID=" +  sessionID + " job-name=" + sessionName)


    def test_sovs(self):
        driver = self.driver
        driver.get(self.base_url + "/~hhl/foundationTest/")
        driver.find_element_by_link_text("grid.html").click()
        self.assertEqual("tekst i ramme 1", driver.find_element_by_css_selector("div.ramme").text)
        self.assertEqual("tekst i ramme 2", driver.find_element_by_css_selector("div.small-6.columns > div.ramme").text)
        self.assertEqual("tekst i ramme 3", driver.find_element_by_xpath("//div[3]/div").text)
        driver.get(self.base_url + "/~hhl/foundationTest/")
        driver.find_element_by_link_text("foundation/").click()
        driver.find_element_by_css_selector("input[type=\"text\"]").clear()
        driver.find_element_by_css_selector("input[type=\"text\"]").send_keys("Her skal der skrives")
        driver.find_element_by_link_text("Alert Btn").click()
        try:
            self.assertEqual("Her skal der skrives",
                             driver.find_element_by_css_selector("input[type=\"text\"]").get_attribute("value"))
        except AssertionError as e:
            self.verificationErrors.append(str(e))
        self.assertEqual("Foundation | Welcome", driver.title)
        driver.get(self.base_url + "/~hhl/foundationTest/")
        driver.find_element_by_link_text("css/").click()
        driver.find_element_by_link_text("app.css").click()
        driver.get(self.base_url + "/~hhl/foundationTest/css/")
        driver.find_element_by_link_text("Parent Directory").click()



    def is_element_present(self, how, what):
        try: self.driver.find_element(by=how, value=what)
        except NoSuchElementException as e: return False
        return True
    
    def is_alert_present(self):
        try: self.driver.switch_to.alert()
        except NoAlertPresentException as e: return False
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
        finally: self.accept_next_alert = True
    
    def tearDown(self):
        if self.WHERE == 'remote':
            self.sauce_client.jobs.update_job(self.driver.session_id, passed=True)
        self.driver.quit()
        self.assertEqual([], self.verificationErrors)

if __name__ == "__main__":
    if len(sys.argv) > 1:
        #sovsTest.SAUCE_KEY = sys.argv.pop()
        #sovsTest.SAUCE_USERNAME = sys.argv.pop()
        sovsTest.WHERE = sys.argv.pop()
        sovsTest.SAUCE_KEY = os.getenv('SAUCE_API_KEY', 'default_value')
        sovsTest.SAUCE_USERNAME = os.getenv('SAUCE_USERNAME', 'default_value')
        # print ("SAUCE_USERNAME is (" + eVaTest.SAUCE_USERNAME + ")")
        # print ("SAUCE_KEY is (" + eVaTest.SAUCE_KEY + ")")
        # print ("WHERE is (" + eVaTest.WHERE + ")")
    # loader = unittest.TestLoader()
    # tests = loader.discover()
    # runner = XMLTestRunner()
    unittest.main(
        testRunner=xmlrunner.XMLTestRunner(output='test-reports'),
        failfast=False, buffer=False, catchbreak=False
    )
    # runner.run(tests)
    # xmlrunner.XMLTestRunner(verbosity=2,output='fil.xml').run(tests)