import json
import logging
import sys
import time
import mariadb
import selenium
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.common.exceptions import NoSuchElementException

SEPERATOR = '; '
USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'


class ColorFormatter(logging.Formatter):
    COLORS = {
        logging.DEBUG: "\033[94m",
        logging.INFO: "\033[92m",
        logging.WARNING: "\033[93m",
        logging.ERROR: "\033[91m",
        logging.CRITICAL: "\033[95m",
    }
    RESET = "\033[0m"

    def format(self, record):
        color = self.COLORS.get(record.levelno, self.RESET)
        message = super().format(record)
        return f"{color}{message}{self.RESET}"


def create_driver(driver_name: str, user_agent: str = USER_AGENT, *opts):
    if driver_name == 'firefox':
        options = webdriver.FirefoxOptions()
        options.set_preference("general.useragent.override", user_agent)
        for opt in opts:
            options.add_argument(opt)
        driver = webdriver.Firefox(options=options)
    elif driver_name == 'chrome':
        options = webdriver.ChromeOptions()
        options.add_argument(f'--user-agent={user_agent}')
        for opt in opts:
            options.add_argument(opt)
        driver = webdriver.Chrome(options=options)
    else:
        raise ValueError("Unsupported driver")
    return driver


def create_db_connection(config_file='../db_creds.json'):
    # Init db
    with open(config_file, 'r') as f:
        creds = json.load(f)
        f.close()
    try:
        conn = mariadb.connect(
            user=creds['user'],
            password=creds['password'],
            database=creds['database'],
            host=creds['host'] if 'host' in creds else 'localhost',
            port=creds['port'] if 'port' in creds else 3306
        )
    except mariadb.Error as e:
        warning(f"Error occurred while connecting mariadb: {e}")

    return conn.cursor()


def accpet_cookies(driver):
    try:
        accept_btn = driver.find_element(By.CSS_SELECTOR, '#onetrust-accept-btn-handler')
        accept_btn.click()
        time.sleep(0.5)
    except selenium.common.exceptions.NoSuchElementException:
        logging.warning("No cookie acceptance button found.")
    except Exception as e:
        logging.error(f"Error occurred while accepting cookies: {e}")


def crawl_menu_links(*opts):
    cur = create_db_connection()
    # Create table if not exists
    cur.execute("""
                CREATE TABLE IF NOT EXISTS menu_links
                (
                    id
                    INT
                    AUTO_INCREMENT
                    PRIMARY
                    KEY,
                    title
                    VARCHAR
                (
                    255
                ) NOT NULL,
                    link VARCHAR
                (
                    255
                ) NOT NULL UNIQUE
                    )
                """)

    # Init driver
    driver = create_driver('firefox', *opts)

    # Open start page
    driver.get('https://www.jumbo.com/recepten/zoeken')
    time.sleep(0.3)

    # Accept cookies
    accpet_cookies(driver)

    # Init
    selected_page = driver.find_element(By.CSS_SELECTOR, '.page.selected')
    page_num = int(selected_page.find_element(By.CSS_SELECTOR, '.page-text').text)

    while True:
        if page_num > 40:
            logging.info("Reached the maximum number of pages to crawl.")
            break
        logging.info(f"Current page: {page_num}")
        nodes = driver.find_elements(By.CSS_SELECTOR, '.jum-card')
        for node in nodes:
            title = node.find_element(By.CSS_SELECTOR, '.title').text
            link = node.find_element(
                By.CSS_SELECTOR, 'a.card-recipe-link').get_attribute('href')
            try:
                cur.execute("""
                            INSERT INTO menu_links (title, link)
                            VALUES (?, ?) ON DUPLICATE KEY
                            UPDATE link =
                            VALUES (link)
                            """, (title, link))
                conn.commit()  # TEST: commit after each insert
            except mariadb.Error as e:
                logging.error(f"Error occurred while inserting into menu_links: {e}")
                raise Exception(f"Error occurred while inserting into menu_links: {e}")
            logging.info(f"Inserted into menu_links: {title}, {link}")
        logging.info(f"Ended page: {page_num}")
        selected_page = driver.find_element(By.CSS_SELECTOR, '.page.selected + .page')
        page_num = int(selected_page.find_element(By.CSS_SELECTOR, '.page-text').text)
        selected_page.click()
        time.sleep(0.5)

    # Post process
    driver.quit()
    cur.close()


def crawl_menu_info(*opts):
    logging.info("Prepare to crawl menu info")
    # Init
    logging.info("Prepare stage")
    cur = create_db_connection()
    driver = create_driver('firefox', *opts)
    accpet_cookies(driver)

    cur.execute("""
                CREATE TABLE IF NOT EXISTS menu_info
                (
                    id
                    INT
                    AUTO_INCREMENT
                    PRIMARY
                    KEY,
                    name
                    VARCHAR
                (
                    255
                ) NOT NULL,
                    author VARCHAR
                (
                    255
                ),
                    genre VARCHAR
                (
                    255
                ),
                    prepare_time VARCHAR
                (
                    255
                ),
                    person_num VARCHAR
                (
                    255
                ),
                    ingredients TEXT,
                    tag TEXT
                    )
                """)

    logging.info("Retrieving links from db")
    cur.execute("SELECT link FROM menu_links")

    logging.info("Start crawling menu info")
    for (link,) in cur:
        logging.info(f"Processing link: {link}")
        driver.get(link)
        time.sleep(0.5)
        accpet_cookies(driver)

        #TODO: turn genre and tag into two separate tables
        data = {
            "name": driver.find_element(
                By.CSS_SELECTOR, '.recipe-header-inner h1.heading.name').text,
            "author": driver.find_element(
                By.CSS_SELECTOR, '.author-info .author-name').text,
            "genre": driver.find_element(
                By.CSS_SELECTOR,
                '#mainContent > div:nth-child(2) > div > div > article > div.recipe-header-container.has-banners > div > div.info > p:nth-child(2)').text,
            "prepare_time": driver.find_element(
                By.CSS_SELECTOR,
                '#mainContent > div:nth-child(2) > div > div > article > div.recipe-header-container.has-banners > div > div.info > p:nth-child(1)').text,
            "person_num": driver.find_element(By.CSS_SELECTOR,
                                              '.ingredients-container > div.jum-recipe-portion-size-selector.portion-size-selector > div.current-value > span').text,
            "ingredients": SEPERATOR.join(list(map(lambda x: x.text,
                                                   driver.find_elements(By.CSS_SELECTOR,
                                                                        '.ingredients-container li.ingredient')))),
            "tag": SEPERATOR.join(
                list(map(lambda x: x.text, driver.find_elements(By.CSS_SELECTOR, '.labels-container .secondary'))))
        }

        logging.info(f"Process ended: {link}")
        time.sleep(0.5)

    # Post process
    driver.quit()
    cur.close()


if __name__ == "__main__":
    handler = logging.StreamHandler(sys.stdout)
    handler.setFormatter(ColorFormatter("%(asctime)s [%(levelname)s]: %(message)s"))
    logging.basicConfig(
        level=logging.INFO,
        handlers=[
            logging.FileHandler("crawl.log"),
            handler
        ]
    )
    logging.info('Crawl started')
    # crawl_menu_links()
    # crawl_menu_links('--headless')
    crawl_menu_info()
    logging.info('Crawl ended')
