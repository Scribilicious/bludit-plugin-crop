# "Crop" a Bludit Plugin

## Introduction
Be in control of how the images are scaled and make content and templating easy and consistent 🌻.


## Features
- Resize images to a specified scale.
- Avoid using unnecessarily large user images in your templates.
- Determine the quality of the images.
- Cache control for improved performance.
- Created with love ❤️.


## Installation
Just copy the folder "crop" into the Bludit plugin folder "bl-plugins" and then activate the plugin in the Bludit plugin section.


## How to use
Just use the following API call:
```
crop/(w{pixels})/(h{pixels})/(q{percentage})/(s)/{image}
```
| Parameter | Value      | Description        |
|-----------|------------|--------------------|
| w         | pixels     | Image width (optional) |
| h         | pixels     | Image height (optional) |
| q         | percentage | Quality of the image (optional) |
| s         |            | Sharp rescaling, if set the rescaling isn't smoothen (optional) |


Enjoy!

Mr. Bot

Ps. If you like my work [support me](https://www.buymeacoffee.com/iambot) 🥹