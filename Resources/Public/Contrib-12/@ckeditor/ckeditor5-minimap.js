import{Plugin as C}from"@ckeditor/ckeditor5-core";import{toUnit as f,global as s,Rect as g,findClosestScrollableAncestor as S}from"@ckeditor/ckeditor5-utils";import{IframeView as P,View as w}from"@ckeditor/ckeditor5-ui";import{ViewDomConverter as E,ViewRenderer as D}from"@ckeditor/ckeditor5-engine";/**
* @license Copyright (c) 2003-2025, CKSource Holding sp. z o.o. All rights reserved.
* For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-licensing-options
*/const b=f("px");class y extends P{_options;constructor(e,i){super(e);const t=this.bindTemplate;this.set("top",0),this.set("height",0),this._options=i,this.extendTemplate({attributes:{tabindex:-1,"aria-hidden":"true",class:["ck-minimap__iframe"],style:{top:t.to("top",r=>b(r)),height:t.to("height",r=>b(r))}}})}render(){return super.render().then(()=>{this._prepareDocument()})}setHeight(e){this.height=e}setTopOffset(e){this.top=e}_prepareDocument(){const e=this.element.contentWindow.document,i=e.adoptNode(this._options.domRootClone),t=this._options.useSimplePreview?`
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
				${this._options.pageStyles.map(a=>typeof a=="string"?`<style>${a}</style>`:`<link rel="stylesheet" type="text/css" href="${a.href}">`).join(`
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

					${t}
				</style>
			</head>
			<body class="${this._options.extraClasses||""}"></body>
		</html>`;e.open(),e.write(o),e.close(),e.body.appendChild(i)}}/**
* @license Copyright (c) 2003-2025, CKSource Holding sp. z o.o. All rights reserved.
* For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-licensing-options
*/const R=f("px");class T extends w{constructor(e){super(e);const i=this.bindTemplate;this.set("height",0),this.set("top",0),this.set("scrollProgress",0),this.set("_isDragging",!1),this.setTemplate({tag:"div",attributes:{class:["ck","ck-minimap__position-tracker",i.if("_isDragging","ck-minimap__position-tracker_dragging")],style:{top:i.to("top",t=>R(t)),height:i.to("height",t=>R(t))},"data-progress":i.to("scrollProgress")},on:{mousedown:i.to(()=>{this._isDragging=!0})}})}render(){super.render(),this.listenTo(s.document,"mousemove",(e,i)=>{this._isDragging&&this.fire("drag",i.movementY)},{useCapture:!0}),this.listenTo(s.document,"mouseup",()=>{this._isDragging=!1},{useCapture:!0})}setHeight(e){this.height=e}setTopOffset(e){this.top=e}setScrollProgress(e){this.scrollProgress=e}}/**
* @license Copyright (c) 2003-2025, CKSource Holding sp. z o.o. All rights reserved.
* For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-licensing-options
*/class v extends w{_positionTrackerView;_scaleRatio;_minimapIframeView;constructor({locale:e,scaleRatio:i,pageStyles:t,extraClasses:r,useSimplePreview:o,domRootClone:a}){super(e);const l=this.bindTemplate;this._positionTrackerView=new T(e),this._positionTrackerView.delegate("drag").to(this),this._scaleRatio=i,this._minimapIframeView=new y(e,{useSimplePreview:o,pageStyles:t,extraClasses:r,scaleRatio:i,domRootClone:a}),this.setTemplate({tag:"div",attributes:{class:["ck","ck-minimap"]},children:[this._positionTrackerView],on:{click:l.to(this._handleMinimapClick.bind(this)),wheel:l.to(this._handleMinimapMouseWheel.bind(this))}})}destroy(){this._minimapIframeView.destroy(),super.destroy()}get height(){return new g(this.element).height}get scrollHeight(){return Math.max(0,Math.min(this.height,this._minimapIframeView.height)-this._positionTrackerView.height)}render(){super.render(),this._minimapIframeView.render(),this.element.appendChild(this._minimapIframeView.element)}setContentHeight(e){this._minimapIframeView.setHeight(e*this._scaleRatio)}setScrollProgress(e){const i=this._minimapIframeView,t=this._positionTrackerView;if(i.height<this.height)i.setTopOffset(0),t.setTopOffset((i.height-t.height)*e);else{const r=i.height-this.height;i.setTopOffset(-r*e),t.setTopOffset((this.height-t.height)*e)}t.setScrollProgress(Math.round(e*100))}setPositionTrackerHeight(e){this._positionTrackerView.setHeight(e*this._scaleRatio)}_handleMinimapClick(e){const i=this._positionTrackerView;if(e.target===i.element)return;const t=new g(i.element),o=(e.clientY-t.top-t.height/2)/this._minimapIframeView.height;this.fire("click",o)}_handleMinimapMouseWheel(e){this.fire("drag",e.deltaY*this._scaleRatio)}}/**
* @license Copyright (c) 2003-2025, CKSource Holding sp. z o.o. All rights reserved.
* For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-licensing-options
*/function V(n,e){const i=n.editing.view.document,t=i.getRoot(e),r=new E(i),o=new D(r,i.selection),a=n.editing.view.getDomRoot().cloneNode();return r.bindElements(a,t),o.markToSync("children",t),o.markToSync("attributes",t),t.on("change:children",(l,m)=>o.markToSync("children",m)),t.on("change:attributes",(l,m)=>o.markToSync("attributes",m)),t.on("change:text",(l,m)=>o.markToSync("text",m)),o.render(),n.editing.view.on("render",()=>o.render()),n.on("destroy",()=>{r.unbindDomElement(a)}),a}function x(){return Array.from(s.document.styleSheets).map(n=>n.href&&!n.href.startsWith(s.window.location.origin)?{href:n.href}:Array.from(n.cssRules).filter(e=>!(e instanceof CSSMediaRule)).map(e=>e.cssText).join(` 
`))}function d(n){return new g(n===s.document.body?s.window:n)}function M(n){return n===s.document.body?s.window.innerHeight:n.clientHeight}function u(n){return n===s.document.body?s.window:n}function H(n,{insertAt:e}={}){if(typeof document>"u")return;const i=document.head||document.getElementsByTagName("head")[0],t=document.createElement("style");t.type="text/css",window.litNonce&&t.setAttribute("nonce",window.litNonce),e==="top"&&i.firstChild?i.insertBefore(t,i.firstChild):i.appendChild(t),t.styleSheet?t.styleSheet.cssText=n:t.appendChild(document.createTextNode(n))}H(':root{--ck-color-minimap-tracker-background:208,0%,51%;--ck-color-minimap-iframe-outline:#bfbfbf;--ck-color-minimap-iframe-shadow:rgba(0,0,0,.11);--ck-color-minimap-progress-background:#666}.ck.ck-minimap{background:var(--ck-color-base-background);position:absolute;user-select:none}.ck.ck-minimap,.ck.ck-minimap iframe{height:100%;width:100%}.ck.ck-minimap iframe{border:0;box-shadow:0 2px 5px var(--ck-color-minimap-iframe-shadow);margin:0;outline:1px solid var(--ck-color-minimap-iframe-outline);pointer-events:none;position:relative}.ck.ck-minimap .ck.ck-minimap__position-tracker{background:hsla(var(--ck-color-minimap-tracker-background),.2);position:absolute;top:0;transition:background .1s ease-in-out;width:100%;z-index:1}@media (prefers-reduced-motion:reduce){.ck.ck-minimap .ck.ck-minimap__position-tracker{transition:none}}.ck.ck-minimap .ck.ck-minimap__position-tracker:hover{background:hsla(var(--ck-color-minimap-tracker-background),.3)}.ck.ck-minimap .ck.ck-minimap__position-tracker.ck-minimap__position-tracker_dragging,.ck.ck-minimap .ck.ck-minimap__position-tracker.ck-minimap__position-tracker_dragging:hover{background:hsla(var(--ck-color-minimap-tracker-background),.4)}.ck.ck-minimap .ck.ck-minimap__position-tracker.ck-minimap__position-tracker_dragging:after,.ck.ck-minimap .ck.ck-minimap__position-tracker.ck-minimap__position-tracker_dragging:hover:after{opacity:1}.ck.ck-minimap .ck.ck-minimap__position-tracker:after{background:var(--ck-color-minimap-progress-background);border:1px solid var(--ck-color-base-background);border-radius:3px;color:var(--ck-color-base-background);content:attr(data-progress) "%";font-size:10px;opacity:0;padding:2px 4px;position:absolute;right:5px;top:5px;transition:opacity .1s ease-in-out}@media (prefers-reduced-motion:reduce){.ck.ck-minimap .ck.ck-minimap__position-tracker:after{transition:none}}');/**
* @license Copyright (c) 2003-2025, CKSource Holding sp. z o.o. All rights reserved.
* For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-licensing-options
*/class I extends C{static get pluginName(){return"Minimap"}static get isOfficialPlugin(){return!0}_minimapView;_scrollableRootAncestor;_editingRootElement;init(){const e=this.editor;this._minimapView=null,this._scrollableRootAncestor=null,this.listenTo(e.ui,"ready",this._onUiReady.bind(this))}destroy(){super.destroy(),this._minimapView.destroy(),this._minimapView.element.remove()}_onUiReady(){const e=this.editor,i=this._editingRootElement=e.ui.getEditableElement();if(this._scrollableRootAncestor=S(i),!i.ownerDocument.body.contains(i)){e.ui.once("update",this._onUiReady.bind(this));return}this._initializeMinimapView(),this.listenTo(e.editing.view,"render",()=>{e.state==="ready"&&this._syncMinimapToEditingRootScrollPosition()}),this._syncMinimapToEditingRootScrollPosition()}_initializeMinimapView(){const e=this.editor,i=e.locale,t=e.config.get("minimap.useSimplePreview"),r=e.config.get("minimap.container"),o=this._scrollableRootAncestor,a=d(this._editingRootElement).width,m=d(r).width/a,c=this._minimapView=new v({locale:i,scaleRatio:m,pageStyles:x(),extraClasses:e.config.get("minimap.extraClasses"),useSimplePreview:t,domRootClone:V(e)});c.render(),c.listenTo(s.document,"scroll",(k,p)=>{if(o===s.document.body){if(p.target!==s.document)return}else if(p.target!==o)return;this._syncMinimapToEditingRootScrollPosition()},{useCapture:!0,usePassive:!0}),c.listenTo(s.window,"resize",()=>{this._syncMinimapToEditingRootScrollPosition()}),c.on("drag",(k,p)=>{let h;c.scrollHeight===0?h=0:h=p/c.scrollHeight;const _=h*(o.scrollHeight-M(o));u(o).scrollBy(0,Math.round(_))}),c.on("click",(k,p)=>{const h=p*o.scrollHeight;u(o).scrollBy(0,Math.round(h))}),r.appendChild(c.element)}_syncMinimapToEditingRootScrollPosition(){const e=this._editingRootElement,i=this._minimapView;i.setContentHeight(e.offsetHeight);const t=d(e),r=d(this._scrollableRootAncestor);let o;r.getIntersection(t)&&(r.contains(t)||t.top>r.top?o=0:(o=(t.top-r.top)/(r.height-t.height),o=Math.max(0,Math.min(o,1))),i.setPositionTrackerHeight(r.getIntersection(t).height),i.setScrollProgress(o))}}export{I as Minimap,y as _MinimapIframeView,T as _MinimapPositionTrackerView,v as _MinimapView,V as _cloneMinimapEditingViewDomRoot,M as _getMinimapClientHeight,d as _getMinimapDomElementRect,x as _getMinimapPageStyles,u as _getMinimapScrollable};
