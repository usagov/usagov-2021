import pandas as pd
import numpy as np
import datetime as dt
import xlsxwriter
import os.path
import sys
import webbrowser

# to access the API:
import searchconsole

########### GCP Authentication ###########

# check if we have the credentials.json file in our directory:
path = './credentials.json'
check_file = os.path.isfile(path) # returns true if it exists.

if check_file is True:
    account = searchconsole.authenticate(client_config='client_secret_94136681046-ckl7ucgrdql7fkb1p2r9pp14nhq9adhu.apps.googleusercontent.com.json',
                                         credentials='credentials.json',
                                         flow='console')
else:
    # run below if this is the first time, to save credentials to credentials.json and avoid authorization again.
    print(webbrowser._browsers)
    account = searchconsole.authenticate(client_config='gsc_api_secret_cred.json',
                                          serialize='credentials.json')

##################################################################
##################################################################

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
    return account[str(property_name)]

########### Functions: Extracting our Data ###########
# defining our function to extract GSC data:
def extract_gsc_data(webproperty, start, stop, *args):
 if webproperty is not None:
   print(f'Extracting data for {webproperty}')
   gsc_data = webproperty.query.range(start, stop).dimension(*args).get()
   return gsc_data
 else:
   print('Webproperty not found, please select the correct one')
   return None
 
# extracting our GSC data, with our selected fields and dates.

# function to validate date matches format:
def validate_date(date_string):
    try:
        # Try to parse the date string into a datetime object
        dt.datetime.strptime(date_string, "%Y-%m-%d")
        return True
    except ValueError:
        # If parsing fails, it means the format is incorrect
        return False

def name_gsc_file(date_res_input, webproperty_input):
    
    name_predicate = "-Google-" + str(date_res_input.month).zfill(2) + "-" + str(date_res_input.day).zfill(2) + "-" + str(date_res_input.year) + "_1.xlsx"
    
    if "www.usa.gov/es/" in str(webproperty_input):
        file_name_result = "SP" + name_predicate
    elif "www.usa.gov/" in str(webproperty_input):
        file_name_result = "EN" + name_predicate
    else:
        file_name_result = "error"
    return file_name_result

########### Functions: Cleaning our Data ###########
# rename our columns to match Medallia format:
def format_gsc_df(df_data):
    df_data = df_data.rename(columns = {'query':'Search Query', # format is oldName:newName
                        'clicks':'Clicks',
                        'impressions':'Impressions',
                        'ctr':'CTR',
                        'position':'Average Position'})
    
    # remove the 'date' column:
    df_data = df_data.drop('date',
                 axis=1) # axis = 1 for columns; 0 for rows
    # format Average Position:
    df_data.iloc[:,4] = df_data.iloc[:,4].round(1)
    return df_data

########### Functions: Exporting our Data ###########

def export_gsc_data(df_data):
    with pd.ExcelWriter(file_name) as writer:
        df_data.to_excel(writer, sheet_name='Sheet1', index=False)
        percent_format = writer.book.add_format({'num_format': '0.00%'})
        worksheet = writer.book.worksheets_objs[0]
        for col in ['D']:
            worksheet.set_column(f'{col}:{col}', None, percent_format)
    print("Done.")

########### Functions: Continuing to download data ###########

def continue_input_with_validation(message_prompt):
    valid_continue_input = ["y", "yes", "no", "n"]
    while True:  
        continue_input = input(message_prompt).lower()
        if continue_input in valid_continue_input:
            print(f"Valid input received: {continue_input}")
            return continue_input
            # same as print("Valid input received: " + user_input)
            break
        else:
            print("Invalid input. Please try again.")
            
            
def continue_input_validation_tf(message_prompt):
    valid_continue_input = ["y", "yes", "no", "n"]
    cont_input_yes = ['y', 'yes']
    cont_input_no = ['n', 'no']
    while True:  
        continue_input2 = input(message_prompt).lower()
        if continue_input2 in valid_continue_input:
            print(f"Valid input received: {continue_input2}")
            
            if continue_input2 in cont_input_yes:
                return True
            elif continue_input2 in cont_input_no:
                return False
            break
        else:
            print("Invalid input. Please try again.")

##################################################################
##################################################################


cont_input_no = ['n','no']

# while loop so that script does not have to be repeatedly run:
while True:
    ########### Selecting GSC Property ###########
    webproperty = set_property(get_language_input())
    
    ########### Extracting our Data ###########
    print("Please answer the prompts to begin downloading data for a selected date range.")
        
    # prompt user to input a start date. Reprompt until valid date is entered:
    while True:
        
        # input_start_date = input("Please enter a start date in YYYY-MM-DD format: ")
        input_start_date = sys.argv[2]

        if validate_date(input_start_date): # our validation formula
            print(f"Valid date: {input_start_date}")
            break
        else:
            print("Invalid date or format. Please use YYYY-MM-DD format.")
    
    
    # prompt user to input an end date. Reprompt until valid date is entered:
    while True:
        # input_end_date = input("Please enter an end date in YYYY-MM-DD format: ")
        input_end_date = sys.argv[3]

        if validate_date(input_end_date): # our validation formula
            print(f"Valid date: {input_end_date}")
            break
        else:
            print("Invalid date or format. Please use YYYY-MM-DD format.")

    
    # get one date at a time in between the selected dates:
    dates_pd_range = pd.date_range(input_start_date,
                  input_end_date,
                  freq='d')
    
    print("We will attempt to download " + str(len(dates_pd_range)) +
          " days worth of data (" + str(len(dates_pd_range))+" files).")
    
    # cont_input = continue_input_with_validation("Continue? (Y/N): ")
    # if cont_input in cont_input_no:
    #     print("Stopped.")
    #     break
        
    continue_with_dates = continue_input_validation_tf("Continue? (Y/N): ")
    
    if continue_with_dates:
    
        for date_value in dates_pd_range:
            ex = extract_gsc_data(webproperty, # we defined this above
                                  date_value, # start_date
                                  date_value, # end_date.
                                  'query',
                                  'date')
            # convert our data to dataframe
            df = pd.DataFrame(data=ex)
            
            # name our file
            file_name = name_gsc_file(date_value, webproperty)
            try:
                ########### Cleaning our Data ###########
                df = format_gsc_df(df)
                ########### Exporting our Data ###########
                export_gsc_data(df)
            except KeyError:
                # in the case where a GSC does not have data for the entered date yet:
                print("Google Search Console does not have data for this date yet.")
                
    ########### Continue? ###########
    # selecting "no" would end the while loop.
    
    cont_input = continue_input_with_validation("Download more data? (Y/N): ")
    
    if cont_input in cont_input_no:
        print("Stopped. Thanks!")
        break
    