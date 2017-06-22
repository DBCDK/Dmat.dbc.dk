# -*- coding: utf-8 -*-
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import Select
from selenium.common.exceptions import NoSuchElementException
from selenium.common.exceptions import NoAlertPresentException
import unittest, time, re

class Ide(unittest.TestCase):
    def setUp(self):
        self.driver = webdriver.Firefox()
        self.driver.implicitly_wait(30)
        self.base_url = "http://devel7.dbc.dk/"
        self.verificationErrors = []
        self.accept_next_alert = True
    
    def test_ide(self):
        driver = self.driver
        driver.get(self.base_url + "/~hhl/Dmat.dbc.dk/eVALU/eVa.php?cmd=show&seqno=50675")
        for i in range(60):
            try:
                if "seqno:50675" == driver.find_element_by_id("seqno50675").text: break
            except: pass
            time.sleep(1)
        else: self.fail("time out")
        self.assertEqual("seqno:50675", driver.find_element_by_id("seqno50675").text)
    
    def is_element_present(self, how, what):
        try: self.driver.find_element(by=how, value=what)
        except NoSuchElementException as e: return False
        return True
    
    def is_alert_present(self):
        try: self.driver.switch_to_alert()
        except NoAlertPresentException as e: return False
        return True
    
    def close_alert_and_get_its_text(self):
        try:
            alert = self.driver.switch_to_alert()
            alert_text = alert.text
            if self.accept_next_alert:
                alert.accept()
            else:
                alert.dismiss()
            return alert_text
        finally: self.accept_next_alert = True
    
    def tearDown(self):
        self.driver.quit()
        self.assertEqual([], self.verificationErrors)

if __name__ == "__main__":
    unittest.main()
