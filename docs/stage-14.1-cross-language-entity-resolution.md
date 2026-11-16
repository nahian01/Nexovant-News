# Stage 14.1 — Cross-Language Entity Resolution & Discovery

## 1. Problem Definition
When international news breaks in non-English languages (such as Bengali or Arabic), foreign institutional and corporate names are transliterated or translated into localized script:
- English: `Bangladesh Bank` $\leftrightarrow$ Bengali: `বাংলাদেশ ব্যাংক`
- English: `Reserve` $\leftrightarrow$ Bengali: `রিজার্ভ`
- English: `Foreign Exchange` $\leftrightarrow$ Bengali: `বৈদেশিক মুদ্রা`

Analysts searching in English must effortlessly discover and correlate stories published originally in foreign scripts without fragmenting intelligence graphs.

## 2. Cross-Language Resolution Architecture
The `CrossLanguageEntityResolver` executes three synchronization passes:
1. **Canonical Entity Alias Matching**: Checks `companies.aliases` and `company_executives` JSON dictionaries across supported scripts.
2. **Translation Bridge**: Evaluates the translated English title and summary alongside the original text.
3. **Graph Clustering**: Both the original article and translated version map to the same `story_clusters` and `companies` relational nodes.

## 3. End-to-End Discovery Verification
In our automated test and live UI verification:
1. Ingested Bengali article:
   - Title: `বাংলাদেশ ব্যাংকের মুদ্রানীতি বিবৃতি: রেপো সুদহার ৮.৫০ শতাংশে অপরিবর্তিত`
   - Content: In Bengali script detailing Bangladesh Bank monetary policy.
2. English Search Query:
   - Query: `"Bangladesh Bank"` or `"Bangladesh"`
3. Resolution Result:
   - Retrieved Bengali article via English translation index.
   - Displayed original Bengali title and summary in UI.
   - Attached badge: `Translated (BN → EN)`.
   - Verified that cluster linked the event to Bangladesh Bank entity node.
