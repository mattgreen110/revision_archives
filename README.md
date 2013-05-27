Revision Archives
=================

This is an ExpressionEngine extension that documents all entry revisions by generating a PDF on entry additions/edits using PDF Crowd's API http://pdfcrowd.com/

I created this as a solution during the site development of a publicly traded company that is regulated by the FDA. Due to regularly scheduled audits there was a need for an automated system that documents every revision that doesn't live just within the database. 

There are a few settings available within the control panel: 

1. PDF Crowd API key (you can get one here http://pdfcrowd.com/)
2. Channel selection
3. File output path
4. Display images?
5. Max image width

This is a very simple and specific extension that basically has one job to do so few people will require it. If one of those people are you, then enjoy! I only support the third party fieldtypes the site I was working on was using (Matrix, Wygwam, Solspace Tag, Solspace Calendar, Playa) but depending on how the data is stored within the exp_channel_data table it may display just fine for any other fieldtypes.

There are two config variables available: 

$config['ra_api_key'] = '';
$config['ra_output_folder'] = '';

If you are looking for more PDF Crowd integration features that utilize template tags and front end functionality then look here http://devot-ee.com/add-ons/pdfcrowd. 

