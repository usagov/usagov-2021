from google.oauth2 import service_account
from googleapiclient.discovery import build
import sys

credentials_path = "gsc_api_secret_cred.json"
project_id = "tts-usagov-mltesting"
bucket_name = "experimenting_with_automl_storage_bucket"
SCOPES = ['https://www.googleapis.com/auth/webmasters.readonly']

credentials = service_account.Credentials.from_service_account_file(credentials_path, scopes=SCOPES)

service = build('webmasters', 'v3', credentials=credentials)

def list_all_files_bucket():
    # Set client to access GCP storage bucket
    client = storage.Client(credentials=credentials, project=project_id)
    bucket = client.bucket(bucket_name)

    # List blobs in the bucket
    blobs = bucket.list_blobs()
    for blob in blobs:
        print(blob.name)

def read_bucket_file_data():
    # Set client to access GCP storage bucket
    client = storage.Client(credentials=credentials, project=project_id)
    bucket = client.bucket(bucket_name)

    # List and download blobs in the bucket
    blobs = list(bucket.list_blobs())
    blob = blobs[0]
    print(blob.download_as_text())

########### Functions: Selecting GSC Property ###########
def get_language_input():
    valid_languages = ["english", "spanish"]
    while True:
        # lang_input = input('Type `English` or `Spanish` to select the corresponding USAGov Search Console property: ').lower()
        lang_input = sys.argv[1]

        if lang_input in valid_languages:
            print(f"Valid input received: {lang_input}")
            return lang_input
            # same as print("Valid input received: " + user_input)
            break
        else:
            print("Invalid input. Please try again.")

def set_property(lang_input_result):
    if lang_input_result == 'spanish':
        property_name = 'https://www.usa.gov/es/'
    elif lang_input_result == 'english':
        property_name = 'https://www.usa.gov/'
    print('You have selected the following property: ' + property_name)
    return property_name
    # return account[str(property_name)]

# print(credentials)
# list_all_files_bucket()
# read_bucket_file_data()

request = {
    'startDate': '2025-02-10',
    'endDate': '2025-02-15',
    'dimensions': ['query'],
}

siteURL = set_property(get_language_input())
response = service.searchanalytics().query(siteUrl=siteURL, body=request).execute()

print(response)