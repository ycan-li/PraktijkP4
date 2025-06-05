import json
import logging
import random
import sys
import time
import mariadb
import selenium
import requests
import io
from PIL import Image
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.common.exceptions import NoSuchElementException

SEPERATOR = '; '
USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'
HEADLESS = False
with open('../request.json', 'r') as f:
    json_data = json.load(f)
    HEADERS = json_data['headers'] if 'headers' in json_data else {}
    COOKIES = json_data['cookies'] if 'cookies' in json_data else {}
    f.close()


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


def create_driver(driver_name: str, user_agent: str = USER_AGENT, headless: bool = False, *opts):
    if driver_name == 'firefox':
        options = webdriver.FirefoxOptions()
        options.set_preference("general.useragent.override", user_agent)
        if headless:
            options.add_argument('--headless')
        for opt in opts:
            options.add_argument(opt)
        driver = webdriver.Firefox(options=options)
    elif driver_name == 'chrome':
        options = webdriver.ChromeOptions()
        options.add_argument(f'--user-agent={user_agent}')
        if headless:
            options.add_argument('--headless')
        driver = webdriver.Chrome(options=options)
    else:
        raise ValueError("Unsupported driver")
    return driver


def create_db_connection(db_name="", config_file='../db_creds.json'):
    # Init db
    with open(config_file, 'r') as f:
        creds = json.load(f)
        f.close()
    try:
        conn = mariadb.connect(
            user=creds['user'],
            password=creds['password'],
            database=db_name if db_name != "" else creds['database'],
            host=creds['host'] if 'host' in creds else 'localhost',
            port=creds['port'] if 'port' in creds else 3306
        )
    except mariadb.Error:
        raise
    return conn


def accept_cookies(driver):
    try:
        accept_btn = driver.find_element(By.CSS_SELECTOR, '#onetrust-accept-btn-handler')
        accept_btn.click()
        logging.info("Accepted cookies")
        time.sleep(0.5)
    except selenium.common.exceptions.NoSuchElementException:
        return
    except Exception as e:
        logging.error(f"Error occurred while accepting cookies: {e}")


def drop_table(conn, table):
    cur = conn.cursor()
    cur.execute(f"DROP TABLE IF EXISTS `{table}`")
    logging.warning(f'Dropped table: {table}')
    conn.commit()
    logging.info(f"Commited change to table: {table}")
    cur.close()


def table_exists(conn_info, table):
    cur = conn_info.cursor()
    cur.execute("""
                SELECT COUNT(*)
                FROM tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                """, (table,))
    exists = cur.fetchone()[0] == 1
    cur.close()
    return exists


def insert_and_get_id(cur, table, col, value, id_col='id'):
    cur.execute(
        f"SELECT `{id_col}` FROM `{table}` WHERE `{col}` = ?",
        (value,)
    )
    row = cur.fetchone()
    if row:
        row_id = row[0]
    else:
        cur.execute(
            f"INSERT INTO `{table}` (`{col}`) VALUES (?)",
            (value,)
        )
        cur.execute("SELECT LAST_INSERT_ID()")
        row_id = cur.fetchone()[0]
        logging.info(f"Inserted {value} into table `{table}` with id `{row_id}`")

    return row_id


def insert_tags(cur_info, cur_target, tags, table_tags, table_target, identity_col, identity, prefix):
    tag_count = len(tags)
    # Get all existing columns with the prefix
    cur_info.execute("""
                     SELECT COLUMN_NAME
                     FROM COLUMNS
                     WHERE TABLE_NAME = ?
                       AND COLUMN_NAME LIKE ?
                     ORDER BY COLUMN_NAME
                     """, (table_target, f"{prefix}%"))
    existing_cols = {row[0] for row in cur_info.fetchall()}

    # Add missing columns
    for idx in range(1, tag_count + 1):
        col = f'{prefix}{idx}'
        if col not in existing_cols:
            try:
                cur_target.execute(f"ALTER TABLE {table_target} ADD COLUMN {col} INT")
                logging.info(f'Added column {col} to table `{table_target}`')
            except mariadb.Error as e:
                logging.error(f"Error occurred while adding column {col}: {e}")
                raise

    # Insert tag values
    for idx, tag in enumerate(tags):
        tag_id = insert_and_get_id(cur_target, table_tags, 'name', tag)
        col = f'{prefix}{idx + 1}'
        sql = f"UPDATE {table_target} SET {col} = ? WHERE {identity_col} = ?"
        cur_target.execute(sql, (tag_id, identity))
        logging.info(f"Updated `{col}` in `{table_target}` for `{identity}` with `{tag_id}` => `{tag}`")


def compress_image(img_data, quality=70):
    img = Image.open(io.BytesIO(img_data)).convert("RGB")
    while quality > 10:
        buffer = io.BytesIO()
        img.save(buffer, format='WEBP', quality=quality, method=6, effort=4)
        data = buffer.getvalue()
        if len(data) <= 65535:
            logging.info(f"Compressed image succeed, quality: {quality}")
            return data
        quality -= 5
    logging.warning("Failed to compress image to fit in 65535 bytes")
    return False


# Crawler
def crawl_menu_links(*opts):
    conn = create_db_connection(db_name="wejv_meta")
    cur = conn.cursor()
    # Create table if not exists
    # menu_links
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
    driver = create_driver(driver_name='firefox', headless=HEADLESS, *opts)

    # Open start page
    driver.get('https://www.jumbo.com/recepten/zoeken')
    time.sleep(0.3)

    # Accept cookies
    accept_cookies(driver)

    # Init
    selected_page = driver.find_element(By.CSS_SELECTOR, '.page.selected')
    page_num = int(selected_page.find_element(By.CSS_SELECTOR, '.page-text').text)

    # Fetch links from the first 40 pages
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


def crawl_menu_info(overwrite=False, *opts):
    logging.info("Prepare to crawl menu info")
    # Init
    logging.info("Prepare stage")
    conn = create_db_connection(db_name="wejv_meta")
    conn_target = create_db_connection(db_name="wejv")
    conn_info = create_db_connection(db_name="information_schema")
    cur = conn.cursor()
    cur_target = conn_target.cursor()
    cur_info = conn_info.cursor()
    driver = create_driver(driver_name='firefox', headless=HEADLESS, *opts)
    accept_cookies(driver)

    if overwrite:
        drop_table(conn_target, "menu_info")
        drop_table(conn_target, "genre")
        drop_table(conn_target, "tags")

    # Create tables if no exists
    # menu_info
    cur_target.execute("""
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
                           prepare_time VARCHAR
                       (
                           255
                       ),
                           person_num VARCHAR
                       (
                           255
                       ),
                           ingredients TEXT,
                           img BLOB
                           )
                       """)
    table_exists(conn_info, "menu_info")
    # genre
    cur_target.execute("""
                       CREATE TABLE IF NOT EXISTS genre
                       (
                           id
                           INTEGER
                           AUTO_INCREMENT
                           PRIMARY
                           KEY,
                           name
                           VARCHAR
                       (
                           255
                       ) NOT NULL
                           )
                       """)
    table_exists(conn_info, "genre")
    # tags
    cur_target.execute("""
                       CREATE TABLE IF NOT EXISTS tags
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
                       ) NOT NULL UNIQUE
                           )
                       """)
    table_exists(conn_info, "tags")

    conn_target.commit()

    logging.info("Retrieving data from db")
    cur.execute("""
                SELECT title, link
                FROM menu_links
                """)

    logging.info("Start crawling menu info")
    count = 0
    for (title, link) in cur:
        try:
            logging.info(f"Processing link: {link}")
            # Check if the link is already processed
            if not overwrite:
                cur_target.execute("""
                                   SELECT name
                                   from menu_info
                                   WHERE name = ?
                                   """, (title,))
                if cur_target.fetchone():
                    logging.info(f"Link already processed: {link}")
                    continue

            driver.get(link)
            time.sleep(0.5)
            accept_cookies(driver)

            data = {
                "name": driver.find_element(
                    By.CSS_SELECTOR, '.recipe-header-inner h1.heading.name').text,
                "author": driver.find_element(
                    By.CSS_SELECTOR, '.author-info .author-name').text,
                "genre": [x.strip() for x in driver.find_element(
                    By.CSS_SELECTOR,
                    '#mainContent > div:nth-child(2) > div > div > article > div.recipe-header-container.has-banners > div > div.info > p:nth-child(2)').text.split(
                    '/')],
                "prepare_time": driver.find_element(
                    By.CSS_SELECTOR,
                    '#mainContent > div:nth-child(2) > div > div > article > div.recipe-header-container.has-banners > div > div.info > p:nth-child(1)').text,
                "person_num": driver.find_element(By.CSS_SELECTOR,
                                                  '.ingredients-container > div.jum-recipe-portion-size-selector.portion-size-selector > div.current-value > span').text,
                "ingredients": SEPERATOR.join(list(map(lambda x: x.text,
                                                       driver.find_elements(By.CSS_SELECTOR,
                                                                            '.ingredients-container li.ingredient')))),
                "tags": [x.text for x in driver.find_elements(By.CSS_SELECTOR, '.labels-container .secondary')],
                "img_url": driver.find_element(By.CSS_SELECTOR, '.recipe-header-container .main-image').get_attribute('src')
            }

            # Insert data into menu_info table without genre and tag_id
            cur_target.execute("""
                               INSERT INTO menu_info (name, author, prepare_time, person_num, ingredients)
                               VALUES (?, ?, ?, ?, ?)
                               """, (data['name'], data['author'], data['prepare_time'], data['person_num'],
                                     data['ingredients']))

            # Process genre_id
            insert_tags(cur_info, cur_target, data['genre'], 'genre', 'menu_info', 'name', data['name'], 'genre_id_')
            # Process tag_id
            insert_tags(cur_info, cur_target, data['tags'], 'tags', 'menu_info', 'name', data['name'], 'tag_id_')

            # Process image
            if not data['img_url']:
                logging.warning(f"No image for {data['name']}")
                continue
            response = requests.get(data['img_url'], headers=HEADERS)
            if response.status_code == 200:
                img_data = compress_image(response.content)
                cur_target.execute("""
                                   UPDATE menu_info
                                   SET img = ?
                                   WHERE name = ?
                                   """, (img_data, data['name']))
            else:
                logging.error(f"Failed to retrieve image for {data['name']}")

            conn.commit()
            conn_target.commit()

            count += 1
            logging.info(f"Process ended: {link}, count: {count}")
            if count % 50 == 0:
                logging.warning('Pausing for 2 minutes')
                time.sleep(60 * 2)  # Sleep for 2 minutes every 50 items to avoid rate limiting
            else:
                time.sleep(random.randint(1, 5))
        except Exception as e:
            logging.error(f"Error occurred while processing {link}: {e}")
            continue

    # Post process
    driver.quit()
    cur.close()


if __name__ == "__main__":
    handler = logging.StreamHandler(sys.stdout)
    handler.setFormatter(ColorFormatter("%(asctime)s [%(levelname)s]: %(message)s"))
    logging.basicConfig(
        level=logging.INFO,
        handlers=[
            logging.FileHandler("crawl.log", mode='w', encoding='utf-8'),
            handler
        ]
    )
    HEADLESS = True
    logging.info('Crawl started')
    # crawl_menu_links()
    # crawl_menu_links('--headless')
    crawl_menu_info(overwrite=False)
    logging.info('Crawl ended')
