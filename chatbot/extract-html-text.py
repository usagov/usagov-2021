#pylint: disable=missing-module-docstring, line-too-long, invalid-name
"""
Parse useful text from USAgov static site html files into files containing
the relevant data from each html file, without the html stuff - just a line
of text for each relevant html element
"""

import os
import re
from bs4 import BeautifulSoup

SKIP_TEXT = set([
    "Find an office near you:",
    "Contact:",
    "Website:",
    "Phone number:",
    "Ask a real person any government-related question for free. They will get you the answer or let you know where to find it.",
    "SHARE THIS PAGE:",
    "LAST UPDATED:"
])

CSS_CLASSES = set(['usa-prose', 'usa-card__body', 'life-events-item-content', 'usagov-directory-table'])

ROOT_PATH = '.'
INPUT_PATH = os.path.join(ROOT_PATH, 'html')
OUTPUT_PATH = os.path.join(ROOT_PATH, 'chatbot/output')

EXCLUDE_DIRS = {'es', 'espanol', 'sites', 'core', 'modules', 'themes', 's3', '_data'}
html_files = []
for root, dirs, files in os.walk(INPUT_PATH, topdown=True):
    dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
    for file in files:
        if file.endswith(".html"):
            html_files.append(os.path.join(root, file))

for html_file in html_files:
    with open(html_file, 'r', encoding='utf-8') as file:
        html_content = file.read()

    page_path = os.path.split(os.path.split(html_file)[0])[1]
    output_file = os.path.join(OUTPUT_PATH, page_path + '.dat')

    num_items_written = 0
    check_duplicates = []
    with open(output_file, 'w', encoding='utf-8') as ofile:
        soup = BeautifulSoup(html_content, 'html.parser')

        for div in soup.find_all('div', class_=CSS_CLASSES):
            for item in div.find_all(['p', 'span', 'a']):
                text = item.get_text()
                if text not in check_duplicates:
                    check_duplicates.append(text)
                    if text not in SKIP_TEXT:
                        text = re.sub(r"\s+|\r+|\n+|\t+", " ", text)
                        text = " ".join(text.split())
                        print(text, file=ofile)
                        num_items_written += 1

    if num_items_written == 0:
        os.remove(output_file)
