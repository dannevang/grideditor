{
	"title": "My First GridEditor",

	"templates": ["BaseTemplate"],

	"filter": {
			"field": "tv_AnotherComboTv",
			"label": "Colour"
		},

	"search": ["pagetitle"],

	"fields": [{
			"name": "pagetitle",
			"title": "Product",
			"editable": true
		},{
			"name": "hidemenu",
			"title": "Checkbox",
			"editable": true
		},{
			"name": "template",
			"title": "Template",
			"editable": true,
			"editor": "modx-combo-template"
		}],

	"tvs": [{
			"name": "MyTvName",
			"editable": false,
			"title": "Text TV"
		},{
			"name": "AnotherComboTv",
			"title": "Combo TV",
			"editable": true
		}],

	"controls": ["publish","edit","delete"]
}