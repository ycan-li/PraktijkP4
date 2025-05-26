import time
import re
import urllib.parse
from pprint import pprint
from random import randint
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.common.exceptions import NoSuchElementException

WARNING_COLOR = "\033[93m"  # Yellow
RESET_COLOR = "\033[0m"      # Reset to default color


def warning(msg: str) -> None:
    print(f"{WARNING_COLOR}Warning: {msg}{RESET_COLOR}")


def crawl(*opts):
    options = webdriver.FirefoxOptions()
    if opts:
        options.add_argument(' '.join(opts))
    driver = webdriver.Firefox(options=options)

    # Open start page
    driver.get('https://www.jumbo.com/recepten/zoeken')
    time.sleep(1)
    all_menu_links = list(map(lambda x: x.get_attribute('href'), driver.find_elements(
        By.CSS_SELECTOR, 'a.card-recipe-link')))

    count = 1
    # Menu page
    for menu_link in all_menu_links:
        driver.get(menu_link)
        print("NO.{count}: Get '{link}'".format(count=count, link=menu_link))
        count +=1

        data = {}
        data["name"] = driver.find_element(
            By.CSS_SELECTOR, '.recipe-header-inner h1.heading.name').text
        data["author"] = driver.find_element(
            By.CSS_SELECTOR, '.author-info .author-name').text
        data["genre"] = driver.find_element(
            By.CSS_SELECTOR, '#mainContent > div:nth-child(2) > div > div > article > div.recipe-header-container.has-banners > div > div.info > p:nth-child(2)').text
        data["prepare_time"] = driver.find_element(
            By.CSS_SELECTOR, '#mainContent > div:nth-child(2) > div > div > article > div.recipe-header-container.has-banners > div > div.info > p:nth-child(1)').text
        data["person_num"] = driver.find_element(By.CSS_SELECTOR, '.ingredients-container > div.jum-recipe-portion-size-selector.portion-size-selector > div.current-value > span').text
        data["ingredients"] = list(map(lambda x: x.text, driver.find_elements(By.CSS_SELECTOR, '.ingredients-container li.ingredient')))

        pprint(data)

        time.sleep(0.25)


if __name__ == "__main__":
    crawl()
