import{Plugin as M}from"@ckeditor/ckeditor5-core";import{global as s,Rect as d,findClosestScrollableAncestor as P,toUnit as u}from"@ckeditor/ckeditor5-utils";import{IframeView as C,View as _}from"@ckeditor/ckeditor5-ui";import{ViewDomConverter as S,ViewRenderer as x}from"@ckeditor/ckeditor5-engine";/**
* @license Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
* For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-licensing-options
*//**
* @license Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
* For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-licensing-options
*/const f=u("px");var w=class extends C{_options;constructor(t,e){super(t);const i=this.bindTemplate;this.set("top",0),this.set("height",0),this._options=e,this.extendTemplate({attributes:{tabindex:-1,"aria-hidden":"true",class:["ck-minimap__iframe"],style:{top:i.to("top",o=>f(o)),height:i.to("height",o=>f(o))}}})}render(){return super.render().then(()=>{this._prepareDocument()})}setHeight(t){this.height=t}setTopOffset(t){this.top=t}_prepareDocument(){const t=this.element.contentWindow.document,e=t.adoptNode(this._options.domRootClone),i=this._options.useSimplePreview?`
			.ck.ck-editor__editable_inline img {
				filter: contrast( 0 );
			}

			p, li, a, figcaption, span {
				background: hsl(0, 0%, 80%) !important;
				color: hsl(0, 0%, 80%) !important;
			}

			h1, h2, h3, h4 {
				background: hsl(0, 0%, 60%) !important;
				color: hsl(0, 0%, 60%) !important;
			}
		`:"",o=`<!DOCTYPE html><html lang="en">
			<head>
				<meta charset="utf-8">
				<meta name="viewport" content="width=device-width, initial-scale=1">
				${this._options.pageStyles.map(n=>typeof n=="string"?`<style>${n}</style>`:`<link rel="stylesheet" type="text/css" href="${n.href}">`).join(`
`)}
				<style>
					html, body {
						margin: 0 !important;
						padding: 0 !important;
					}

					html {
						overflow: hidden;
					}

					body {
						transform: scale( ${this._options.scaleRatio} );
						transform-origin: 0 0;
						overflow: visible;
					}

					.ck.ck-editor__editable_inline {
						margin: 0 !important;
						border-color: transparent !important;
						outline-color: transparent !important;
						box-shadow: none !important;
					}

					.ck.ck-content {
						background: white;
					}

					${i}
				</style>
			</head>
			<body class="${this._options.extraClasses||""}"></body>
		</html>`;t.open(),t.write(o),t.close(),t.body.appendChild(e)}};/**
* @license Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
* For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-licensing-options
*/const b=u("px");var y=class extends _{constructor(t){super(t);const e=this.bindTemplate;this.set("height",0),this.set("top",0),this.set("scrollProgress",0),this.set("_isDragging",!1),this.setTemplate({tag:"div",attributes:{class:["ck","ck-minimap__position-tracker",e.if("_isDragging","ck-minimap__position-tracker_dragging")],style:{top:e.to("top",i=>b(i)),height:e.to("height",i=>b(i))},"data-progress":e.to("scrollProgress")},on:{mousedown:e.to(()=>{this._isDragging=!0})}})}render(){super.render(),this.listenTo(s.document,"mousemove",(t,e)=>{this._isDragging&&this.fire("drag",e.movementY)},{useCapture:!0}),this.listenTo(s.document,"mouseup",()=>{this._isDragging=!1},{useCapture:!0})}setHeight(t){this.height=t}setTopOffset(t){this.top=t}setScrollProgress(t){this.scrollProgress=t}};/**
* @license Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
* For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-licensing-options
*/var R=class extends _{_positionTrackerView;_scaleRatio;_minimapIframeView;constructor({locale:t,scaleRatio:e,pageStyles:i,extraClasses:o,useSimplePreview:n,domRootClone:r}){super(t);const l=this.bindTemplate;this._positionTrackerView=new y(t),this._positionTrackerView.delegate("drag").to(this),this._scaleRatio=e,this._minimapIframeView=new w(t,{useSimplePreview:n,pageStyles:i,extraClasses:o,scaleRatio:e,domRootClone:r}),this.setTemplate({tag:"div",attributes:{class:["ck","ck-minimap"]},children:[this._positionTrackerView],on:{click:l.to(this._handleMinimapClick.bind(this)),wheel:l.to(this._handleMinimapMouseWheel.bind(this))}})}destroy(){this._minimapIframeView.destroy(),super.destroy()}get height(){return new d(this.element).height}get scrollHeight(){return Math.max(0,Math.min(this.height,this._minimapIframeView.height)-this._positionTrackerView.height)}render(){super.render(),this._minimapIframeView.render(),this.element.appendChild(this._minimapIframeView.element)}setContentHeight(t){this._minimapIframeView.setHeight(t*this._scaleRatio)}setScrollProgress(t){const e=this._minimapIframeView,i=this._positionTrackerView;if(e.height<this.height)e.setTopOffset(0),i.setTopOffset((e.height-i.height)*t);else{const o=e.height-this.height;e.setTopOffset(-o*t),i.setTopOffset((this.height-i.height)*t)}i.setScrollProgress(Math.round(t*100))}setPositionTrackerHeight(t){this._positionTrackerView.setHeight(t*this._scaleRatio)}_handleMinimapClick(t){const e=this._positionTrackerView;if(t.target===e.element)return;const i=new d(e.element),o=(t.clientY-i.top-i.height/2)/this._minimapIframeView.height;this.fire("click",o)}_handleMinimapMouseWheel(t){this.fire("drag",t.deltaY*this._scaleRatio)}};/**
* @license Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
* For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-licensing-options
*/function T(t,e){const i=t.editing.view.document,o=i.getRoot(e),n=new S(i),r=new x(n,i.selection),l=t.editing.view.getDomRoot().cloneNode();return n.bindElements(l,o),r.markToSync("children",o),r.markToSync("attributes",o),o.on("change:children",(a,c)=>r.markToSync("children",c)),o.on("change:attributes",(a,c)=>r.markToSync("attributes",c)),o.on("change:text",(a,c)=>r.markToSync("text",c)),r.render(),t.editing.view.on("render",()=>r.render()),t.on("destroy",()=>{n.unbindDomElement(l)}),l}function k(){return Array.from(s.document.styleSheets).map(t=>t.href&&!t.href.startsWith(s.window.location.origin)?{href:t.href}:Array.from(t.cssRules).filter(e=>!(e instanceof CSSMediaRule)).map(e=>e.cssText).join(` 
`))}function p(t){return new d(t===s.document.body?s.window:t)}function V(t){return t===s.document.body?s.window.innerHeight:t.clientHeight}function g(t){return t===s.document.body?s.window:t}/**
* @license Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
* For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-licensing-options
*/var E=class extends M{static get pluginName(){return"Minimap"}static get isOfficialPlugin(){return!0}_minimapView;_scrollableRootAncestor;_editingRootElement;init(){const t=this.editor;this._minimapView=null,this._scrollableRootAncestor=null,this.listenTo(t.ui,"ready",this._onUiReady.bind(this))}destroy(){super.destroy(),this._minimapView.destroy(),this._minimapView.element.remove()}_onUiReady(){const t=this.editor,e=this._editingRootElement=t.ui.getEditableElement();if(this._scrollableRootAncestor=P(e),!e.ownerDocument.body.contains(e)){t.ui.once("update",this._onUiReady.bind(this));return}this._initializeMinimapView(),this.listenTo(t.editing.view,"render",()=>{t.state==="ready"&&this._syncMinimapToEditingRootScrollPosition()}),this._syncMinimapToEditingRootScrollPosition()}_initializeMinimapView(){const t=this.editor,e=t.locale,i=t.config.get("minimap.useSimplePreview"),o=t.config.get("minimap.container"),n=this._scrollableRootAncestor,r=p(this._editingRootElement).width,l=p(o).width/r,a=this._minimapView=new R({locale:e,scaleRatio:l,pageStyles:k(),extraClasses:t.config.get("minimap.extraClasses"),useSimplePreview:i,domRootClone:T(t)});a.render(),a.listenTo(s.document,"scroll",(c,h)=>{if(n===s.document.body){if(h.target!==s.document)return}else if(h.target!==n)return;this._syncMinimapToEditingRootScrollPosition()},{useCapture:!0,usePassive:!0}),a.listenTo(s.window,"resize",()=>{this._syncMinimapToEditingRootScrollPosition()}),a.on("drag",(c,h)=>{let m;a.scrollHeight===0?m=0:m=h/a.scrollHeight;const v=m*(n.scrollHeight-V(n));g(n).scrollBy(0,Math.round(v))}),a.on("click",(c,h)=>{const m=h*n.scrollHeight;g(n).scrollBy(0,Math.round(m))}),o.appendChild(a.element)}_syncMinimapToEditingRootScrollPosition(){const t=this._editingRootElement,e=this._minimapView;e.setContentHeight(t.offsetHeight);const i=p(t),o=p(this._scrollableRootAncestor);let n;o.getIntersection(i)&&(o.contains(i)||i.top>o.top?n=0:(n=(i.top-o.top)/(o.height-i.height),n=Math.max(0,Math.min(n,1))),e.setPositionTrackerHeight(o.getIntersection(i).height),e.setScrollProgress(n))}};export{E as Minimap,w as _MinimapIframeView,y as _MinimapPositionTrackerView,R as _MinimapView,T as _cloneMinimapEditingViewDomRoot,V as _getMinimapClientHeight,p as _getMinimapDomElementRect,k as _getMinimapPageStyles,g as _getMinimapScrollable};
