# marmalade
RESTful API (JSON payload)


I created Quote Entity & Repository in order to use SQL queries for quotation table.
I've seen people getting it done in other ways as well, where they use Repository or Query Builder
Could use Repository in order to get Quote data from DB, because I have created it's Entity already. Could create new Route to "show" Quotes list via JSON Request and etc.  I also used Annotations for routing instead of routes.yaml

Added UK vehicle's number plates checker;
Added UK's postcodes checker;

Included other checks like:
Check if request's JSON is valid (catching error codes -> https://www.php.net/manual/en/function.json-last-error.php )
Check if request's Method is POST
Check if request's Content-Type is application/json
Check if request contain any data & has specific fields (age, postcode, regNo) - could add age range check as well, but I'll skip that

Added 3rd party API connection to get ABI code ( https://api.dvlasearch.co.uk/ -> it is  https://ukvehicledata.co.uk/ )
I couldn't find any website where I could get ABI code, so instead of getting ABI code it gets VIN by regNo.
I know that DVLA could provide some access to use their tools & DB for checking cars and etc., for Developers or companies, there is also other website's, who provide those features, but they charge per JSON/XML Request.
I've seen that 3rd party API could be connected and used via Symfony in other ways;

Added JSON & HTTP Response for every check (error, success)
