# Eight-product nutrition sourcing — traceable inputs, not menu verification

**Status: 0 of 8 cafeteria recipes completed.** The project now includes **20 USDA SR Legacy nutrient-reference candidates** with FDC IDs, per-100-g values for all seven required fields, and review links. These have **not** been promoted into `ingredients.csv` or used to generate product Nutrition Facts: no cafeteria batch measurements, actual ingredient products, cost receipts, or human nutrition sign-off were supplied. Reference coverage is different from completed recipe coverage.

## Evidence and identity checks

- USDA download catalog: <https://fdc.nal.usda.gov/download-datasets/>. SR Legacy is the historical April 2018 type; it has no newer SR Legacy revision.
- FNRI PhilFCT searchable database: <https://i.fnri.dost.gov.ph/fct/library>. Its underlying data have not been downloaded or represented as PhilFCT values here.
- `app/Database/Seeds/data/usda_reference_candidates.csv` contains 20 **specific** SR Legacy IDs, 100-g quantities, and links to USDA details and the **GetFoodFacts mirror pages actually consulted**. An ID alone does not prove that the selected entry matches the brand, raw/cooked state, or edible portion in the cafeteria.
- Every source reference in the library is `USDA FDC #<id> (SR Legacy)`. No USDA data were disguised as Philippine food-composition measurements.
- Use `php spark nutrition:references` to validate local provenance fields and list missing kitchen measurements; use `php spark nutrition:catalog-audit` for the **actual** ingredient/recipe seeder CSVs. A valid reference listing does **not** certify a food or a finished menu product.

## What must be confirmed in the actual kitchen

| Demo product | Reference candidates available | Unresolved facts before nutrition can be calculated |
|---|---|---|
| Chicken Adobo Rice | Chicken, cooked white rice, garlic, onion, *low-sodium* soy sauce, oil | Raw chicken/bone mass, rice portion, **actual** soy sauce, vinegar, seasonings, oil retained, recipe yield, servings |
| Pork Sisig Rice | Pork, rice, onion, mayonnaise, egg | Pork cut, cooked vs raw weighing, actual calamansi, sauces, cooking oil, quantities and portions |
| Fried Chicken Meal | Chicken, rice, flour, egg, oil | Skin/bone/edible yield, marinade, coating and flour used, fryer oil uptake, side dishes |
| Cheese Burger | Ground beef, cheddar, lettuce, tomato, onion, mayonnaise | **Actual bun** and sauces, cooked beef yield, grams per burger, used cheese product |
| Crispy Fries | Raw potatoes, oil | Actual trimmed potato weight, salt, fryer oil uptake and cooked serving mass |
| Iced Spanish Latte | Whole milk, sweetened condensed milk, brewed coffee | Brand/strength of coffee, milk brand, condensed milk and syrup grams, ice content and serving volume |
| Hot Americano | Brewed coffee | Actual espresso and added water quantities; brewed coffee is only a potential nutrient proxy |
| Calamansi Juice | Granulated sugar | **Calamansi-specific** FDC or PhilFCT food, actual juice yield, sugar added, water and serving size |

The reference lemon entry is deliberately named **“not calamansi”**. It is not an approved substitution for calamansi, and the ingredient name and source ID must not be reused as if they were the same fruit. The soy-sauce entry is explicitly **low sodium** and is not assumed to match the cafeteria's sauce. The worksheet lists candidates for **review**, not an observed or adviser-approved recipe.

## Complete the real seed files

1. Open `app/Database/Seeds/data/recipe_measurement_workbook.csv`. For each product, the kitchen must replace suggestions with the **ingredients actually used**, and fill in `confirmed_ingredient`, `measured_quantity`, `measured_unit`, waste percentage, batch servings, cooking yield, packaging cost, and kitchen sign-off. Add missing ingredient rows when necessary. Do **not** copy the worksheet as `recipes.csv`.
2. Look up exact ingredients in the [USDA official FoodData Central](https://fdc.nal.usda.gov/) using the chosen ID and verify all seven nutrient fields. The candidates can reduce searching time. Use `php spark nutrition:ingredient-import` for supported USDA Foundation or SR Legacy records (requires a reachable USDA service), **operator-entered** demo cost and a measured density/grams-per-piece when relevant. The command creates validated entries in `ingredients.csv`.
3. For every **confirmed** recipe line, run `php spark nutrition:recipe-add` with measured quantities and consistent batch fields. This creates `recipes.csv` records; never substitute guessed quantities or blank/zero nutrients.
4. Run `php spark nutrition:catalog-audit`, then complete the **append-only migration** to backfill existing databases with historical nutrition seeder markers. Do not delete seeder registry rows or rerun an old seeder against admin-managed recipes. Test `down()` before release.
5. Calculate per-serving nutrients from the sourced recipes with `CostingService`, validate actual portions against the kitchen, and obtain the existing human verification sign-off. Keep `nutrition_status=estimated` until authorized staff verify it.

The eight-product criteria (measured recipe, actual ingredient match, total nutrients/cost, history-safe migration, and human sign-off) are **blocked by real-world data not supplied in this repository**. Do not mark #5 or the final acceptance suite as Verified on the strength of this reference library.
