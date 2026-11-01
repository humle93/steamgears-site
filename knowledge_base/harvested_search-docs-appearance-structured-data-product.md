# Intro to Product Structured Data on Google | Google Search Central | Documentation | Google for Developers

Kilde: https://developers.google.com/search/docs/appearance/structured-data/product

Skip to main content 

- Search Central 

- English
- Deutsch
- Español
- Español – América Latina
- Français
- Indonesia
- Italiano
- Polski
- Português – Brasil
- Tiếng Việt
- Türkçe
- Русский
- العربيّة
- हिंदी
- ภาษาไทย
- 中文 – 简体
- 中文 – 繁體
- 日本語
- 한국어Sign in

-  Documentation 
Search Console

-  Home 
- 
 Search Central 
- 
 Documentation  Send feedback 

# Introduction to Product structured data
 When you add structured data to your product pages, your product information can appear in richer ways in Google Search results (including Google Images and Google Lens). For example, users can see price, availability, review ratings, shipping information, and more right in search results. 

## Deciding which markup to use
There are two main classes of product structured data. Follow the requirements for the type that best suits your use case: 
- Product snippets: For product pages where people can't directly purchase the product. This markup has more options for specifying review information, like pros and cons on an editorial product review page. 
- Merchant listings: For pages where customers can purchase products from you. This markup has more options for specifying detailed product information, like apparel sizing, shipping details, and return policy information. 
 Note that there is some overlap between the two product features. In general, adding the required product information properties for merchant listings means that your product pages can also be eligible for product snippets. Both features have their own enhancements, so be sure to review both when deciding which markup makes sense in the context of your site (the more properties you can add, the more enhancements your page can be eligible for). 
In addition to structured data for the individual products that you sell, we also recommend you add structured data defining the policies of your ecommerce business, nested under Organization markup:
- Merchant return policy: Specify the return policy (or policies) for your business.
- Loyalty Program: Specify the loyalty program that you offer.

## How shopping experiences can appear on Google Search
 Here's how shopping experiences can appear in Google Search results. This list is not exhaustive—Google Search is constantly exploring new and better ways to help people find what they're looking for, and the experiences may change over time. 
Product snippet
A text result that includes additional product information such as ratings, review information, price, and availability 
Popular products
 Visually rich presentation of products for sale 
Shopping knowledge panel
 Detailed product information with a list of sellers (using details such as product identifiers) 
Google Images
 Annotated images of products available for sale 

### Result enhancements
 Search result enhancements are shown at the discretion of each experience, and may change over time. For this reason, it is recommended to provide as much rich product information as available, without concern for the exact experiences that will use it. Here are some examples of how product rich results may be enhanced: 
- Ratings: Enhance the appearance of your search result by providing customer reviews and ratings.
- Pros and Cons: Identify pros and cons in your product review description so they can be highlighted in search results.
- Shipping: Share shipping costs, especially free shipping, so shoppers understand the total cost.
- Availability: Provide availability data to help customers know when you have a product in stock.
- Price drop: Price drops are computed by Google by observing price changes for the product over time. Price drops are not guaranteed to be shown.
- Returns: Share return information, such as your return policy, fees involved in returns, and how many days customers have to return a product.

## Providing product data to Google Search
 To provide rich product data to Google Search you can add Product structured data to your web pages, upload data feeds with Google Merchant Center and opt into free listings within the Merchant Center console, or both. The Search Central documentation focuses on structured data on web pages. 
 Providing both structured data on web pages and a Merchant Center feed maximizes your eligibility to experiences and helps Google correctly understand and verify your data. Some experiences combine data from structured data and Google Merchant Center feeds if both are available. For example, product snippets may use pricing data from your merchant feed if it's not present in the structured data on the page. The Google Merchant Center feed documentation includes additional recommendations and requirements for feed attributes. 
 In addition to Google Search, learn more about eligibility to the Google Shopping tab by reading the data and eligibility requirements in Google Merchant Center.  Send feedback 

Except as otherwise noted, the content of this page is licensed under the Creative Commons Attribution 4.0 License, and code samples are licensed under the Apache 2.0 License. For details, see the Google Developers Site Policies. Java is a registered trademark of Oracle and/or its affiliates.
Last updated 2025-12-10 UTC.
 Need to tell us more?  [[["Easy to understand","easyToUnderstand","thumb-up"],["Solved my problem","solvedMyProblem","thumb-up"],["Other","otherUp","thumb-up"]],[["Missing the information I need","missingTheInformationINeed","thumb-down"],["Too complicated / too many steps","tooComplicatedTooManySteps","thumb-down"],["Out of date","outOfDate","thumb-down"],["Samples / code issue","samplesCodeIssue","thumb-down"],["Other","otherDown","thumb-down"]],["Last updated 2025-12-10 UTC."],[],["Product structured data enhances how product information appears in Google Search, including Google Images and Lens. Two markup types exist: **Product snippets** for non-purchase pages, emphasizing reviews, and **Merchant listings** for purchase pages, highlighting product details like sizing and shipping. Adding product variant data can help Google. Key actions include adding structured data to webpages, or uploading data feeds to Google Merchant Center to enhance search visibility with details like ratings, shipping, availability, price drops, and return information. Providing both structured data and a Merchant Center feed maximizes product visibility.\n"]]