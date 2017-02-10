## Google Contacts API V3 Client

This is the missing PHP Client for managing a user's contacts using Google Contacts V3 API

## Features

- List All Contacts
- Get a Single Contact
- Search Contacts
- Get a Contact Photo
- Create / Update / Delete Contact
- Batch Create / Update / Delete Contacts

## Installation

In your compose.json file add the following requirements:


```json
{
	"require": {
      "google/apiclient": "^2.0",
      "google/apiclient-services" : "dev-master",
      "guzzlehttp/guzzle": "^6.2"
	},
  "autoload": {
    "psr-4": {
      "amaughan81\\": "vendor/amaughan81/GoogleContacts/src/"
    }
  }
}
```

Then run `composer update`

## Setup

Note this application is designed for a **server-to-server** scenario, so a bit of extra coding will be needed if you are using this application in other scenarios.

1. Ensure the required dependencies are installed.  See above require composer packages.
2. Update the file config.json and complete the values for `secret_path`, `client_path`, `subject` and `developerKey`. These are obtained from the Google Developers Console at : https://console.developers.google.com. The `secret_path` and `client_path` are the paths to JSON files on your server which are obtained from the Google Console.
