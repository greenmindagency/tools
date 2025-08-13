<?php
$title = "WordprSEO Content Generator";
include 'header.php';
?>
<style>
  #output { white-space: pre-wrap; background: #f8f9fa; border: 1px solid #ced4da; padding: 20px; margin-top: 10px; border-radius: 5px; min-height: 200px; }
</style>
<div class="mb-3">
  <label class="form-label">Client Name</label>
  <input type="text" id="client" class="form-control" placeholder="e.g. Green Mind Agency">
</div>
<div class="mb-3">
  <label class="form-label">Number of Pages</label>
  <input type="number" id="pageLimit" class="form-control" min="1" placeholder="e.g. 10">
</div>
<div class="mb-3">
  <label class="form-label">Content Notes</label>
  <textarea id="contentNotes" class="form-control" rows="4" placeholder="Optional. If you uploaded files in the client's folder, no need to add notes here."></textarea>
</div>
<div class="mb-3">
  <label class="form-label">Website Tree (optional)</label>
  <textarea id="tree" class="form-control" rows="4" placeholder="- Home\n- About\n- Services\n  - ..."></textarea>
</div>
<div class="mb-3">
  <label class="form-label">Output Language</label>
  <input type="text" id="language" class="form-control" placeholder="e.g. English">
</div>
<div>
  <button class="btn btn-primary" onclick="generatePrompt()">Generate Prompt</button>
</div>
<div class="d-flex align-items-center mt-5">
  <h3 class="mb-0">Generated Prompt:</h3>
  <button class="btn btn-outline-secondary btn-sm ms-3" onclick="copyPrompt()">Copy</button>
</div>
<div id="output" class="form-control" readonly></div>
<textarea id="clipboardArea" style="position: absolute; left: -9999px; top: -9999px;"></textarea>
<script>
const instructions = `please work as a website content creator and upload the content to WordprSEO a wordpress custom theme targeted for SEO from Green Mind Agency.

Wat we need is to fully check the instructions files or the content i give below from other competetors and websites.

what i need first is to show a website tree first if i'm not providing it chekc below if i add it:

(if i add a website tree) 

what i need after you give me the tree to mention which page is (page/sing;e/category/tag)

in wordprseo already we have below:

- pages: home, about, careers, contact us
- tags (sub service only)
- cat (latest work, clients, blog, news) any thing that we can attach singles too
- single: a case studies or blog pages, news etc.. 

so you have to follow the above instructions to make the new content.

start with the tree first then we can work on page by page 

what i need from you is to follow below sections you have to make pages appeal and have varities of sections, usually make a variations to make things looks nice 

the default template have the below structure that you can follow:

home page:
slider, tagslist, pagecontent2, pagecontent1, catconnection, slider, pagecontent3, articletitle, articleimages, postconnection, hero-video, articletitle, pagecontent7, postsrelatedcat, accordion, pagecontent5

about page:
slider, articletitle, pagecontent2, pagecontent1, catconnection, pagecontent4, tagconnection, postsrelatedcat, articletitle, articleslideshow, pagecontent5

service category which has all services 
hero-video|pagecontent5, tagslist, articletitle, articlevideogallery, postsrelatedtagslider, pagecontent1, pagecontent3, verticaltabs, pagecontent7, pagecontent4, pagecontent2, pagecontent5, catconnection, articletitle, articleimages, tagconnection, postsrelatedcat, articletitle, articlevideogallery, postconnection, pagecontent5

when you see | between 2 tabs means this is a 2 columns of tabs beside each other it can work only in hero video and pagecontent5

for other categories like blog or news it has only 1 section postsrelatedcat and select the infinite checkbox

service tags (sub services) under the service category working as a subservices (tag)
slider, pagecontent1, tagconnection, articletitle, pagecontent1, pagecontent3, pagecontent1, articletitle, articlevideogallery, accordion, articletitle, imgcarousel, pagecontent5

careers page have the pagecontent5 only which is the form

contact us have the contacts section and pagecontent6 for the map

start with the sitemap then step by step we will work on each page content based on below each section requeriments only, please also when you send me a sitemap make what the type if it will be cat, single etc..

the important thing try to don't follow the above structure please, this is for your info how things can be sorted not to copy past the same style sections for all pages, if the content can accept the pages lenght and please try to avoide long pages we did the above structure just for showing how the website is nice in real websites it's not the case.

--- sections description and design:

Section Name: accordion

Layout Structure: Single-column full-width layout.

Title: One main section title at the top (recommended 3–6 words).

Subtitle: One subtitle directly under the main title (recommended 4–8 words).

Content Display: Accordion component with three collapsible panels.

Panels: Each panel has a clickable heading bar with a background color and title text (recommended 2–4 words).

Content Style: When a panel is expanded, it displays a short paragraph (recommended 25–40 words) beneath the heading.

Number of Panels: Three total, can be up to 5.

Interaction: Only one panel is expanded at a time (likely configured via accordion behavior).

--

Section Name: article description

Layout Structure: Single-column full-width layout.

Heading: One section heading (recommended 2–5 words).

Paragraphs: 1–2 short paragraphs (each 20–35 words) describing the article or topic.

Additional Element: May include a subheading above the paragraphs (optional, 3–6 words).

--

Section Name: article images

Layout Structure: Single row with multiple columns (4–6 equal-width image blocks).

Images: Each block contains a single logo or image.

Text: No text overlay, images only.

Recommended Size: Consistent image dimensions for uniform alignment.

--

Section Name: article title

Layout Structure: Single-column full-width layout.

Heading: One main section title (recommended 2–5 words).

Subtitle: One subtitle directly under the title (recommended 5–8 words).

--

Section Name: article video

Layout Structure: Single-column full-width layout.

Content: Embedded video player.

Heading: Optional heading above the video (2–4 words).

Video Dimensions: Full-width or responsive container.

--

Section Name: article video gallery

Layout Structure: Two-column layout.

Content: Each column contains an embedded video player.

Number of Videos: 2–4 per section.

Video Dimensions: Equal height and width for consistent display.

--

Section Name: catconnection

Layout Structure: Two-column layout.

Left Column: Full-height background image.

loaded from the category page that i'll select need only title (3–5 words), and  subtitle (4–8 words) 

--

Section Name: postconnection

Layout Structure: Two-column layout.

Left Column: Full-height background image.

loaded from the post  page that i'll select need only title (3–5 words), and  subtitle (4–8 words) 

--

Section Name: tagconnection

Layout Structure: Two-column layout.

Left Column: Full-height background image.

loaded from the tag  page that i'll select need only title (3–5 words), and  subtitle (4–8 words) 

--

Section Name: contacts

Layout Structure: Two-column layout.

Left Column: Contact information grouped by location, each with an icon for phone and address.

Right Column: Simple form with and submit button, this is loaded from the cf7.

Heading: 2–5 words.

Intro Text: One short paragraph (15–25 words).

--

Section Name: herovideo

Layout Structure: Full-width background video.

Text Overlay: Large title (4–8 words), short subtitle (6–10 words), and 1–2 call-to-action buttons.

--

Section Name: image slider

Layout Structure: Single-row image carousel.

Images: 4–6 logos or images per view.

Navigation: Left/right arrows.

--

Section Name: image carousel

Layout Structure: Full-width rotating image gallery.

Images: 1 large image visible at a time.

Navigation: Dots or arrows for switching.

--

Section Name: pagecontent1

Layout Structure: Single-column text block.

Heading: 4–6 words.

Paragraphs: 2–3 paragraphs (each 25–35 words).

Optional List: Bulleted list (3–5 items, 4–7 words each).

--

Section Name: pagecontent2

Layout Structure: Three equal-width columns.

Each Column: Icon (awesome font icon), heading (2–4 words), and short text (10–20 words).

--

Section Name: pagecontent3

Layout Structure: Full-width background color with centered statistics.

Stats: 3–5 numerical highlights, each with an icon (awesome font icon), number, and label (2–4 words).

--

Section Name: pagecontent4

it can work for the team members or images left with content beside it 

strating with title 5 words and subtitle from 15 to 20 words

Layout Structure: Two-column layout, loop content.

Left Column: Image.

Right Column: related to each image has 1–2 short paragraphs (20–35 words each).

at the end a content ending the section can have a bullet list.

--

Section Name: pagecontent5

Layout Structure: 1-column

a content form section for ending pages can be transfered to a title/ subtitle and a call to action 1 or 2 buttons

--

Section Name: pagecontent6

Layout Structure: 1-column

a map location at the end above it title (5/6 words) and description 15 to 20 words.

--

Section Name: pagecontent7

Layout Structure: 2-columns

a section present % of success 

title (5/6 words) and content under it 15 to 20 words.

at the right a title and % this title showing a service or important figuer with % beside it

--

Section Name: pagecontent8

Layout Structure: Two-column layout.

Left Column: Full-height background image.

Right Column: Section title (4–7 words), subtitle (6–10 words), and accordion list.

Accordion Panels: 3 panels with headings (3–5 words each).

Content Style: When expanded, each panel shows a short paragraph (20–35 words).

Additional Text Block: Short paragraph (20–30 words) below accordion to highlight overall career mission.

Call-to-Action: One button (2–4 words) below text block.

--

Section Name: pagecontent9

Layout Structure: Single-column full-width layout with heading and subtitle centered at the top.

Heading: Large main heading (5–8 words).

Subtitle: Short descriptive line (8–12 words).

Content Display: Three-column grid.

Each Column:

Image on top.

Title (2–4 words) overlay or below image.

Short paragraph (10–20 words).

Call-to-action link or button (2–4 words).

Number of Cards: 3 visible per row.

a hidden content will show up under the Short paragraph the lenght is 

--

Section Name: postsrelatedcat

Layout Structure: Single-column full-width layout.

Heading: Large main heading (2–4 words).

Subtitle: Short descriptive line (8–12 words).

Content Display: Three-column grid, posts related from the category i'll select, no need to do anything, just mention if it should be,infinite loading, or have a button at the end linked to the category.

Number of Cards: 3 visible per row.

--

Section Name: postsrelatedcatslider

Layout Structure: 2-column width layout.

Heading: Large main heading (2–4 words).

Subtitle: Short descriptive line (8–12 words).

Content Display: 1 grid carousel, posts related from the category i'll select, no need to do anything.

Number of Cards: 1 visible per row.

--

Section Name: postsrelatedwithfilter

Layout Structure: Single-column full-width layout.

Heading: Large main heading (3–5 words).

Subtitle: Short descriptive line (8–12 words).

Filters: Tag buttons above grid, please tell me the tags i should select that will filter the posts

--

Section Name: slider

Layout Structure: Full-width image slider.

Each Slide:

Background image.

Title (4–6 words).

Subtitle (6–10 words). 

from 3 to 5 slides make sure to suggest image keyword for search on images stocks websites

--

Section Name: tagslist

Layout Structure: Two-row, two-column grid.

Heading: Large main heading (3–5 words).

Subtitle: Short descriptive line (6–10 words).

Each Service Item: will be selected tags from admin, just mention the tags.

--

Section Name: testimonial

Layout Structure: Single-column full-width layout.

Heading: Large main heading (5–8 words).

Subtitle: Short descriptive line (8–12 words).

Content Display: a testemenials number i'll select and it will show up form the single pages.

--

Section Name: verticaltabs

Layout Structure: Two-column layout.

Left Column: Vertical list of 3–6 tab buttons (3–6 words each).

Right Column:

Image on top make sure to suggest image keyword for search on images stocks websites.

Title (3–5 words).

1–2 short paragraphs (20–30 words each).
`;

function generatePrompt() {
  const client = document.getElementById('client').value.trim();
  const pageLimit = document.getElementById('pageLimit').value.trim();
  const contentNotes = document.getElementById('contentNotes').value.trim();
  const tree = document.getElementById('tree').value.trim();
  const language = document.getElementById('language').value.trim() || 'English';
  let prompt = instructions + "\n\n";
  if (client) {
    prompt += `Client name: ${client}.\n\n`;
  }
  if (contentNotes) {
    prompt += `Content notes:\n${contentNotes}\n\n`;
  } else {
    prompt += "If content files are uploaded in the client's folder, refer to them. No additional content provided here.\n\n";
  }
  prompt += `Output language: ${language}.\n\n`;
  if (!tree) {
    prompt += `Create a website tree for a WordprSEO site${pageLimit ? ` with no more than ${pageLimit} pages, the sub menu items also count, so if you will add tags for example as a sub services under category it will be counted.` : ''}. List each item and note whether it is a page, category, tag, or single. Existing pages: Home, About, Careers, Contact Us.\n\n`;
    prompt += "give me  the website tree in a bullet points list don't extend it too mush please, if i give you the website tree stuck on it, you can give recommendation if you would like, and stop after providing the sitemap.";
  } else {
    prompt += `Website tree:\n${tree}\n\n`;
    if (pageLimit) {
      prompt += `Ensure the sitemap and overall site do not exceed ${pageLimit} pages.\n`;
    }
    prompt += "Please stuck on above tree only, since this is approved from the client.We will create the content page by page.Ask me which page to work on first and follow the section guidelines provided.";
  }
  document.getElementById('output').textContent = prompt;
}

function copyPrompt() {
  const output = document.getElementById('output').textContent;
  const textarea = document.getElementById('clipboardArea');
  textarea.value = output;
  textarea.select();
  document.execCommand('copy');
}
</script>
<?php include 'footer.php'; ?>
