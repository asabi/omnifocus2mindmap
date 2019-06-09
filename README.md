# Convert your Omnifocus 3.0 to a mindmap

## To install:

Clone this repository to a folder of your choice

Example:

```
git clone https://github.com/asabi/omnifocus2mindmap.git ~/Documents/omnifocus2mindmap
```

## run the script:

Note: This scripts does a curl request to a Lambda function on AWS that does the actuall conversion of the information into a mindmap (https://json.tomindmap.com)

```
php ~/Documents/omnifocus2mindmap/of2mm.php 
```

This will generate a new file on your desktop named "omnifocus <date and time>.mm"

You can open this file in one of the following mindmapping tools:

* Freemind (http://freemind.sourceforge.net/wiki/index.php/Main_Page)
* iThoughtx (https://www.toketaware.com/ithoughts-new-home)
* Simplemind - thrugh the import functionality (https://simplemind.eu/)
* xmind - through the import functionality (https://www.xmind.net/)
* Any other mindmapping tool that supports freemind format


## Make it "fancy through a button on Omnifocus

* Make sure the of2mm.php file is executable:

```
chmod +x ~/Documents/omnifocus2mindmap/of2mm.php 
```

* Open script editor on your mac (/Applications/Utilities/Script Editor.app)
* Paste the following (assuming you did not chance any of the other instructions):

```
do shell script "~/Documents/omnifocus2mindmap/of2mm.php"
```

* Save and compile the script to a location of your choice
* Download a png version of the mindmap application of your choice, and make it the script icon (https://9to5mac.com/2019/01/17/change-mac-icons/)
* Copy the script into your Omnifocus scripts folder (Omnifocus -> Help -> Open scripts folder)
* Right click on the toolbar in Omnifocus, and choose customize toolbar
* Drag your script to the toolbar

