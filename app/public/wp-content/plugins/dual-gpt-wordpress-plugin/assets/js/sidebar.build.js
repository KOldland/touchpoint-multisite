function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _regenerator() { /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/babel/babel/blob/main/packages/babel-helpers/LICENSE */ var e, t, r = "function" == typeof Symbol ? Symbol : {}, n = r.iterator || "@@iterator", o = r.toStringTag || "@@toStringTag"; function i(r, n, o, i) { var c = n && n.prototype instanceof Generator ? n : Generator, u = Object.create(c.prototype); return _regeneratorDefine2(u, "_invoke", function (r, n, o) { var i, c, u, f = 0, p = o || [], y = !1, G = { p: 0, n: 0, v: e, a: d, f: d.bind(e, 4), d: function d(t, r) { return i = t, c = 0, u = e, G.n = r, a; } }; function d(r, n) { for (c = r, u = n, t = 0; !y && f && !o && t < p.length; t++) { var o, i = p[t], d = G.p, l = i[2]; r > 3 ? (o = l === n) && (u = i[(c = i[4]) ? 5 : (c = 3, 3)], i[4] = i[5] = e) : i[0] <= d && ((o = r < 2 && d < i[1]) ? (c = 0, G.v = n, G.n = i[1]) : d < l && (o = r < 3 || i[0] > n || n > l) && (i[4] = r, i[5] = n, G.n = l, c = 0)); } if (o || r > 1) return a; throw y = !0, n; } return function (o, p, l) { if (f > 1) throw TypeError("Generator is already running"); for (y && 1 === p && d(p, l), c = p, u = l; (t = c < 2 ? e : u) || !y;) { i || (c ? c < 3 ? (c > 1 && (G.n = -1), d(c, u)) : G.n = u : G.v = u); try { if (f = 2, i) { if (c || (o = "next"), t = i[o]) { if (!(t = t.call(i, u))) throw TypeError("iterator result is not an object"); if (!t.done) return t; u = t.value, c < 2 && (c = 0); } else 1 === c && (t = i.return) && t.call(i), c < 2 && (u = TypeError("The iterator does not provide a '" + o + "' method"), c = 1); i = e; } else if ((t = (y = G.n < 0) ? u : r.call(n, G)) !== a) break; } catch (t) { i = e, c = 1, u = t; } finally { f = 1; } } return { value: t, done: y }; }; }(r, o, i), !0), u; } var a = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} t = Object.getPrototypeOf; var c = [][n] ? t(t([][n]())) : (_regeneratorDefine2(t = {}, n, function () { return this; }), t), u = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(c); function f(e) { return Object.setPrototypeOf ? Object.setPrototypeOf(e, GeneratorFunctionPrototype) : (e.__proto__ = GeneratorFunctionPrototype, _regeneratorDefine2(e, o, "GeneratorFunction")), e.prototype = Object.create(u), e; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, _regeneratorDefine2(u, "constructor", GeneratorFunctionPrototype), _regeneratorDefine2(GeneratorFunctionPrototype, "constructor", GeneratorFunction), GeneratorFunction.displayName = "GeneratorFunction", _regeneratorDefine2(GeneratorFunctionPrototype, o, "GeneratorFunction"), _regeneratorDefine2(u), _regeneratorDefine2(u, o, "Generator"), _regeneratorDefine2(u, n, function () { return this; }), _regeneratorDefine2(u, "toString", function () { return "[object Generator]"; }), (_regenerator = function _regenerator() { return { w: i, m: f }; })(); }
function _regeneratorDefine2(e, r, n, t) { var i = Object.defineProperty; try { i({}, "", {}); } catch (e) { i = 0; } _regeneratorDefine2 = function _regeneratorDefine(e, r, n, t) { function o(r, n) { _regeneratorDefine2(e, r, function (e) { return this._invoke(r, n, e); }); } r ? i ? i(e, r, { value: n, enumerable: !t, configurable: !t, writable: !t }) : e[r] = n : (o("next", 0), o("throw", 1), o("return", 2)); }, _regeneratorDefine2(e, r, n, t); }
function asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; }
function _slicedToArray(r, e) { return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
function _iterableToArrayLimit(r, l) { var t = null == r ? null : "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t.return && (u = t.return(), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }
function _arrayWithHoles(r) { if (Array.isArray(r)) return r; }
/**
 * Dual-GPT Gutenberg Sidebar
 */

var registerPlugin = wp.plugins.registerPlugin;
var PluginSidebar = wp.editPost.PluginSidebar;
var _wp$components = wp.components,
  PanelBody = _wp$components.PanelBody,
  TextareaControl = _wp$components.TextareaControl,
  TextControl = _wp$components.TextControl,
  Button = _wp$components.Button,
  Spinner = _wp$components.Spinner,
  ToggleControl = _wp$components.ToggleControl,
  SelectControl = _wp$components.SelectControl,
  Notice = _wp$components.Notice;
var _wp$element = wp.element,
  useState = _wp$element.useState,
  useEffect = _wp$element.useEffect;
var _wp$data = wp.data,
  useSelect = _wp$data.useSelect,
  useDispatch = _wp$data.useDispatch;
var _wp = wp,
  apiFetch = _wp.apiFetch;
var StatusMessage = function StatusMessage(_ref) {
  var _ref$tone = _ref.tone,
    tone = _ref$tone === void 0 ? 'info' : _ref$tone,
    title = _ref.title,
    children = _ref.children;
  return wp.element.createElement("div", {
    className: "dual-gpt-message dual-gpt-message-".concat(tone)
  }, title ? wp.element.createElement("strong", null, title) : null, children ? wp.element.createElement("div", null, children) : null);
};
var DualGPTSidebar = function DualGPTSidebar() {
  var _dualGptData, _dualGptData2, _dualGptData3, _dualGptData4, _imageResult$attachme2;
  var _useState = useState(''),
    _useState2 = _slicedToArray(_useState, 2),
    researchPrompt = _useState2[0],
    setResearchPrompt = _useState2[1];
  var _useState3 = useState('draft'),
    _useState4 = _slicedToArray(_useState3, 2),
    authorMode = _useState4[0],
    setAuthorMode = _useState4[1];
  var _useState5 = useState(''),
    _useState6 = _slicedToArray(_useState5, 2),
    frameworkBriefId = _useState6[0],
    setFrameworkBriefId = _useState6[1];
  var _useState7 = useState(''),
    _useState8 = _slicedToArray(_useState7, 2),
    plannerSessionId = _useState8[0],
    setPlannerSessionId = _useState8[1];
  var _useState9 = useState(''),
    _useState0 = _slicedToArray(_useState9, 2),
    authorInstructions = _useState0[0],
    setAuthorInstructions = _useState0[1];
  var _useState1 = useState({
      industry_focus: ((_dualGptData = dualGptData) === null || _dualGptData === void 0 || (_dualGptData = _dualGptData.coreSettings) === null || _dualGptData === void 0 ? void 0 : _dualGptData.industry_focus) || 'General',
      audience_tier: ((_dualGptData2 = dualGptData) === null || _dualGptData2 === void 0 || (_dualGptData2 = _dualGptData2.coreSettings) === null || _dualGptData2 === void 0 ? void 0 : _dualGptData2.audience_tier) || 'General',
      risk_tolerance: ((_dualGptData3 = dualGptData) === null || _dualGptData3 === void 0 || (_dualGptData3 = _dualGptData3.coreSettings) === null || _dualGptData3 === void 0 ? void 0 : _dualGptData3.risk_tolerance) || 'Moderate',
      brand_profile: ((_dualGptData4 = dualGptData) === null || _dualGptData4 === void 0 || (_dualGptData4 = _dualGptData4.coreSettings) === null || _dualGptData4 === void 0 ? void 0 : _dualGptData4.brand_profile) || 'Brand A (FSI)'
    }),
    _useState10 = _slicedToArray(_useState1, 2),
    authorCoreSettings = _useState10[0],
    setAuthorCoreSettings = _useState10[1];
  var _useState11 = useState(false),
    _useState12 = _slicedToArray(_useState11, 2),
    researchLoading = _useState12[0],
    setResearchLoading = _useState12[1];
  var _useState13 = useState(false),
    _useState14 = _slicedToArray(_useState13, 2),
    authorLoading = _useState14[0],
    setAuthorLoading = _useState14[1];
  var _useState15 = useState(''),
    _useState16 = _slicedToArray(_useState15, 2),
    researchResults = _useState16[0],
    setResearchResults = _useState16[1];
  var _useState17 = useState(''),
    _useState18 = _slicedToArray(_useState17, 2),
    authorResults = _useState18[0],
    setAuthorResults = _useState18[1];
  var _useState19 = useState(''),
    _useState20 = _slicedToArray(_useState19, 2),
    researchError = _useState20[0],
    setResearchError = _useState20[1];
  var _useState21 = useState(''),
    _useState22 = _slicedToArray(_useState21, 2),
    authorError = _useState22[0],
    setAuthorError = _useState22[1];
  var _useState23 = useState(null),
    _useState24 = _slicedToArray(_useState23, 2),
    researchJobId = _useState24[0],
    setResearchJobId = _useState24[1];
  var _useState25 = useState(null),
    _useState26 = _slicedToArray(_useState25, 2),
    authorJobId = _useState26[0],
    setAuthorJobId = _useState26[1];
  var _useState27 = useState([]),
    _useState28 = _slicedToArray(_useState27, 2),
    authorBlocks = _useState28[0],
    setAuthorBlocks = _useState28[1];
  var _useState29 = useState(null),
    _useState30 = _slicedToArray(_useState29, 2),
    authorAbstract = _useState30[0],
    setAuthorAbstract = _useState30[1];
  var _useState31 = useState([]),
    _useState32 = _slicedToArray(_useState31, 2),
    authorWarnings = _useState32[0],
    setAuthorWarnings = _useState32[1];
  var _useState33 = useState([]),
    _useState34 = _slicedToArray(_useState33, 2),
    authorValidationErrors = _useState34[0],
    setAuthorValidationErrors = _useState34[1];
  var _useState35 = useState(null),
    _useState36 = _slicedToArray(_useState35, 2),
    imageConfig = _useState36[0],
    setImageConfig = _useState36[1];
  var _useState37 = useState(true),
    _useState38 = _slicedToArray(_useState37, 2),
    imageConfigLoading = _useState38[0],
    setImageConfigLoading = _useState38[1];
  var _useState39 = useState(''),
    _useState40 = _slicedToArray(_useState39, 2),
    imageConfigError = _useState40[0],
    setImageConfigError = _useState40[1];
  var _useState41 = useState(false),
    _useState42 = _slicedToArray(_useState41, 2),
    imageRecommendationLoading = _useState42[0],
    setImageRecommendationLoading = _useState42[1];
  var _useState43 = useState(false),
    _useState44 = _slicedToArray(_useState43, 2),
    imageGenerateLoading = _useState44[0],
    setImageGenerateLoading = _useState44[1];
  var _useState45 = useState(''),
    _useState46 = _slicedToArray(_useState45, 2),
    imageError = _useState46[0],
    setImageError = _useState46[1];
  var _useState47 = useState(''),
    _useState48 = _slicedToArray(_useState47, 2),
    imageNotice = _useState48[0],
    setImageNotice = _useState48[1];
  var _useState49 = useState(null),
    _useState50 = _slicedToArray(_useState49, 2),
    imageRecommendation = _useState50[0],
    setImageRecommendation = _useState50[1];
  var _useState51 = useState(null),
    _useState52 = _slicedToArray(_useState51, 2),
    imageResult = _useState52[0],
    setImageResult = _useState52[1];
  var _useState53 = useState(''),
    _useState54 = _slicedToArray(_useState53, 2),
    imagePrompt = _useState54[0],
    setImagePrompt = _useState54[1];
  var _useState55 = useState(''),
    _useState56 = _slicedToArray(_useState55, 2),
    imageNegativePrompt = _useState56[0],
    setImageNegativePrompt = _useState56[1];
  var _useState57 = useState(''),
    _useState58 = _slicedToArray(_useState57, 2),
    imageAltText = _useState58[0],
    setImageAltText = _useState58[1];
  var _useState59 = useState(''),
    _useState60 = _slicedToArray(_useState59, 2),
    imageCaption = _useState60[0],
    setImageCaption = _useState60[1];
  var _useState61 = useState(''),
    _useState62 = _slicedToArray(_useState61, 2),
    imageTextInImage = _useState62[0],
    setImageTextInImage = _useState62[1];
  var _useState63 = useState(false),
    _useState64 = _slicedToArray(_useState63, 2),
    imageEditorialAccuracy = _useState64[0],
    setImageEditorialAccuracy = _useState64[1];
  var _useState65 = useState(true),
    _useState66 = _slicedToArray(_useState65, 2),
    imageSetFeatured = _useState66[0],
    setImageSetFeatured = _useState66[1];
  var _useState67 = useState(true),
    _useState68 = _slicedToArray(_useState67, 2),
    imageStoreMedia = _useState68[0],
    setImageStoreMedia = _useState68[1];
  var _useState69 = useState('16:9'),
    _useState70 = _slicedToArray(_useState69, 2),
    imageAspectRatio = _useState70[0],
    setImageAspectRatio = _useState70[1];
  var _useState71 = useState('4K'),
    _useState72 = _slicedToArray(_useState71, 2),
    imageSize = _useState72[0],
    setImageSize = _useState72[1];
  var _useState73 = useState('google'),
    _useState74 = _slicedToArray(_useState73, 2),
    imageProvider = _useState74[0],
    setImageProvider = _useState74[1];
  var _useState75 = useState('layered_editorial_cutout'),
    _useState76 = _slicedToArray(_useState75, 2),
    imagePresetKey = _useState76[0],
    setImagePresetKey = _useState76[1];
  var _useState77 = useState(''),
    _useState78 = _slicedToArray(_useState77, 2),
    imageAdditionalKeywords = _useState78[0],
    setImageAdditionalKeywords = _useState78[1];
  var _useDispatch = useDispatch('core/block-editor'),
    insertBlocks = _useDispatch.insertBlocks;
  var _useDispatch2 = useDispatch('core/editor'),
    editPost = _useDispatch2.editPost;
  var _useDispatch3 = useDispatch('core/notices'),
    createNotice = _useDispatch3.createNotice;
  var postId = useSelect(function (select) {
    return select('core/editor').getCurrentPostId();
  }, []);
  var draftContent = useSelect(function (select) {
    return select('core/editor').getEditedPostContent();
  }, []);
  var postTitle = useSelect(function (select) {
    return select('core/editor').getEditedPostAttribute('title') || '';
  }, []);
  var postExcerpt = useSelect(function (select) {
    return select('core/editor').getEditedPostAttribute('excerpt') || '';
  }, []);
  useEffect(function () {
    var loadImageConfig = /*#__PURE__*/function () {
      var _ref2 = _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee() {
        var _response$house_style, _response$workflow, _response$workflow2, response, _t;
        return _regenerator().w(function (_context) {
          while (1) switch (_context.p = _context.n) {
            case 0:
              setImageConfigLoading(true);
              setImageConfigError('');
              _context.p = 1;
              _context.n = 2;
              return apiFetch({
                path: 'dual-gpt/v1/images/config',
                method: 'GET'
              });
            case 2:
              response = _context.v;
              setImageConfig(response);
              setImageProvider(response.image_provider || 'google');
              setImagePresetKey(response.default_preset_key || 'layered_editorial_cutout');
              setImageAspectRatio(((_response$house_style = response.house_style) === null || _response$house_style === void 0 ? void 0 : _response$house_style.aspect_ratio) || '16:9');
              setImageStoreMedia(!!((_response$workflow = response.workflow) !== null && _response$workflow !== void 0 && _response$workflow.auto_store_media));
              setImageSetFeatured(!!((_response$workflow2 = response.workflow) !== null && _response$workflow2 !== void 0 && _response$workflow2.allow_featured_image_replace));
              _context.n = 4;
              break;
            case 3:
              _context.p = 3;
              _t = _context.v;
              setImageConfigError((_t === null || _t === void 0 ? void 0 : _t.message) || 'Failed to load image settings.');
            case 4:
              _context.p = 4;
              setImageConfigLoading(false);
              return _context.f(4);
            case 5:
              return _context.a(2);
          }
        }, _callee, null, [[1, 3, 4, 5]]);
      }));
      return function loadImageConfig() {
        return _ref2.apply(this, arguments);
      };
    }();
    loadImageConfig();
  }, []);
  var handleResearchSubmit = /*#__PURE__*/function () {
    var _ref3 = _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee2() {
      var sessionResponse, jobResponse, errorMessage, _t2;
      return _regenerator().w(function (_context2) {
        while (1) switch (_context2.p = _context2.n) {
          case 0:
            if (researchPrompt.trim()) {
              _context2.n = 1;
              break;
            }
            setResearchError('Please enter a research prompt');
            return _context2.a(2);
          case 1:
            setResearchLoading(true);
            setResearchError('');
            setResearchResults('');
            _context2.p = 2;
            _context2.n = 3;
            return apiFetch({
              path: 'dual-gpt/v1/sessions',
              method: 'POST',
              data: {
                role: 'research',
                title: 'Research Session - ' + new Date().toLocaleString()
              }
            });
          case 3:
            sessionResponse = _context2.v;
            _context2.n = 4;
            return apiFetch({
              path: 'dual-gpt/v1/jobs',
              method: 'POST',
              data: {
                session_id: sessionResponse.session_id,
                prompt: researchPrompt,
                model: 'gpt-4'
              }
            });
          case 4:
            jobResponse = _context2.v;
            setResearchJobId(jobResponse.job_id);
            setResearchResults('Job submitted successfully. Processing...');
            _pollJobStatus(jobResponse.job_id, 'research');
            _context2.n = 6;
            break;
          case 5:
            _context2.p = 5;
            _t2 = _context2.v;
            errorMessage = 'An error occurred while processing your research request.';
            if (_t2.code === 'budget_exceeded') {
              errorMessage = 'Token budget exceeded. Please contact an administrator.';
            } else if (_t2.code === 'invalid_api_key') {
              errorMessage = 'API configuration error. Please contact an administrator.';
            } else if (_t2.message) {
              errorMessage = _t2.message;
            }
            setResearchError(errorMessage);
            createNotice('error', errorMessage, {
              type: 'snackbar'
            });
          case 6:
            _context2.p = 6;
            setResearchLoading(false);
            return _context2.f(6);
          case 7:
            return _context2.a(2);
        }
      }, _callee2, null, [[2, 5, 6, 7]]);
    }));
    return function handleResearchSubmit() {
      return _ref3.apply(this, arguments);
    };
  }();
  var handleAuthorSubmit = /*#__PURE__*/function () {
    var _ref4 = _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee3() {
      var payload, response, _response$output, _response$output2, _response$output3, errorMessage, _t3;
      return _regenerator().w(function (_context3) {
        while (1) switch (_context3.p = _context3.n) {
          case 0:
            setAuthorLoading(true);
            setAuthorError('');
            setAuthorResults('');
            setAuthorBlocks([]);
            setAuthorAbstract(null);
            setAuthorWarnings([]);
            setAuthorValidationErrors([]);
            _context3.p = 1;
            payload = {
              mode: authorMode,
              framework_brief_id: frameworkBriefId || undefined,
              planner_session_id: plannerSessionId || undefined,
              draft_content: authorMode !== 'draft' ? draftContent : undefined,
              instructions: authorInstructions || undefined,
              core_settings: authorCoreSettings
            };
            _context3.n = 2;
            return apiFetch({
              path: 'dual-gpt/v1/author/run',
              method: 'POST',
              data: payload
            });
          case 2:
            response = _context3.v;
            setAuthorWarnings(response.warnings || []);
            setAuthorValidationErrors(response.validation_errors || []);
            if (response.mode === 'draft') {
              setAuthorBlocks(((_response$output = response.output) === null || _response$output === void 0 ? void 0 : _response$output.blocks) || []);
              setAuthorResults('Draft completed successfully.');
            } else if (response.mode === 'abstract') {
              setAuthorAbstract(((_response$output2 = response.output) === null || _response$output2 === void 0 ? void 0 : _response$output2.abstract) || null);
              setAuthorResults('Abstract completed successfully.');
            } else if (response.mode === 'enrichment') {
              setAuthorBlocks(((_response$output3 = response.output) === null || _response$output3 === void 0 ? void 0 : _response$output3.blocks) || []);
              setAuthorResults('Enrichment completed successfully.');
            }
            _context3.n = 4;
            break;
          case 3:
            _context3.p = 3;
            _t3 = _context3.v;
            errorMessage = 'An error occurred while processing your authoring request.';
            if (_t3.code === 'budget_exceeded') {
              errorMessage = 'Token budget exceeded. Please contact an administrator.';
            } else if (_t3.code === 'invalid_api_key') {
              errorMessage = 'API configuration error. Please contact an administrator.';
            } else if (_t3.message) {
              errorMessage = _t3.message;
            }
            setAuthorError(errorMessage);
            createNotice('error', errorMessage, {
              type: 'snackbar'
            });
          case 4:
            _context3.p = 4;
            setAuthorLoading(false);
            return _context3.f(4);
          case 5:
            return _context3.a(2);
        }
      }, _callee3, null, [[1, 3, 4, 5]]);
    }));
    return function handleAuthorSubmit() {
      return _ref4.apply(this, arguments);
    };
  }();
  var _pollJobStatus = /*#__PURE__*/function () {
    var _ref5 = _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee4(jobId, type) {
      var response, errorMsg, _errorMsg, _t4;
      return _regenerator().w(function (_context4) {
        while (1) switch (_context4.p = _context4.n) {
          case 0:
            _context4.p = 0;
            _context4.n = 1;
            return apiFetch({
              path: "dual-gpt/v1/jobs/".concat(jobId),
              method: 'GET'
            });
          case 1:
            response = _context4.v;
            if (response.status === 'completed') {
              if (type === 'research') {
                setResearchResults('Research completed successfully.');
              } else {
                setAuthorResults('Content generation completed successfully.');
              }
            } else if (response.status === 'failed') {
              errorMsg = response.error_message || 'Job failed';
              if (type === 'research') {
                setResearchError(errorMsg);
              } else {
                setAuthorError(errorMsg);
              }
              createNotice('error', "Job failed: ".concat(errorMsg), {
                type: 'snackbar'
              });
            } else {
              setTimeout(function () {
                return _pollJobStatus(jobId, type);
              }, 2000);
            }
            _context4.n = 3;
            break;
          case 2:
            _context4.p = 2;
            _t4 = _context4.v;
            _errorMsg = 'Error checking job status';
            if (type === 'research') {
              setResearchError(_errorMsg);
            } else {
              setAuthorError(_errorMsg);
            }
          case 3:
            return _context4.a(2);
        }
      }, _callee4, null, [[0, 2]]);
    }));
    return function pollJobStatus(_x, _x2) {
      return _ref5.apply(this, arguments);
    };
  }();
  var insertBlocksFromAuthor = function insertBlocksFromAuthor() {
    if (!authorBlocks || authorBlocks.length === 0) {
      return;
    }
    var escapeHtml = function escapeHtml(value) {
      if (value === null || value === undefined) {
        return '';
      }
      return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };
    var buildPullquoteMetaSpan = function buildPullquoteMetaSpan(meta) {
      if (!meta || _typeof(meta) !== 'object') {
        return '';
      }
      var attributes = ["data-source-author=\"".concat(escapeHtml(meta.source_author || ''), "\""), "data-publication=\"".concat(escapeHtml(meta.publication || ''), "\""), "data-organisation=\"".concat(escapeHtml(meta.organisation || ''), "\""), "data-date=\"".concat(escapeHtml(meta.date || ''), "\""), "data-citation-ref-id=\"".concat(escapeHtml(meta.citation_ref_id || ''), "\"")];
      return "<span class=\"dual-gpt-pullquote-meta\" style=\"display:none\" ".concat(attributes.join(' '), "></span>");
    };
    var blocks = authorBlocks.map(function (block) {
      switch (block.type) {
        case 'heading':
          return wp.blocks.createBlock('core/heading', {
            level: block.level || 2,
            content: block.content || ''
          });
        case 'paragraph':
          return wp.blocks.createBlock('core/paragraph', {
            content: block.content || ''
          });
        case 'list':
          var listItems = (block.items || []).map(function (item) {
            return "<li>".concat(escapeHtml(item), "</li>");
          }).join('');
          var listTag = block.ordered ? 'ol' : 'ul';
          return wp.blocks.createBlock('core/list', {
            ordered: !!block.ordered,
            values: "<".concat(listTag, ">").concat(listItems, "</").concat(listTag, ">")
          });
        case 'pullquote':
          var pullquoteMeta = buildPullquoteMetaSpan(block.meta || block.metadata);
          return wp.blocks.createBlock('core/pullquote', {
            value: "<p>".concat(escapeHtml(block.content || ''), "</p>").concat(pullquoteMeta),
            citation: escapeHtml(block.cite || '')
          });
        case 'quote':
          return wp.blocks.createBlock('core/quote', {
            value: "<p>".concat(escapeHtml(block.content || ''), "</p>"),
            citation: escapeHtml(block.cite || '')
          });
        case 'separator':
          return wp.blocks.createBlock('core/separator', {});
        default:
          return wp.blocks.createBlock('core/paragraph', {
            content: block.content || ''
          });
      }
    });
    insertBlocks(blocks);
  };
  var buildImagePayload = function buildImagePayload() {
    return {
      post_id: postId,
      title: postTitle,
      summary: postExcerpt || '',
      prompt: imagePrompt,
      negative_prompt: imageNegativePrompt,
      alt_text: imageAltText,
      caption: imageCaption,
      provider: imageProvider,
      preset_key: imagePresetKey,
      keywords: imageAdditionalKeywords,
      text_in_image: imageTextInImage,
      editorial_accuracy: imageEditorialAccuracy,
      store_in_media_library: imageStoreMedia,
      set_featured_image: imageSetFeatured,
      aspect_ratio: imageAspectRatio,
      image_size: imageSize
    };
  };
  var handleRecommendImage = /*#__PURE__*/function () {
    var _ref6 = _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee5() {
      var response, _t5;
      return _regenerator().w(function (_context5) {
        while (1) switch (_context5.p = _context5.n) {
          case 0:
            setImageRecommendationLoading(true);
            setImageError('');
            setImageNotice('');
            _context5.p = 1;
            _context5.n = 2;
            return apiFetch({
              path: 'dual-gpt/v1/images/recommend',
              method: 'POST',
              data: buildImagePayload()
            });
          case 2:
            response = _context5.v;
            setImageRecommendation(response);
            setImagePrompt(response.prompt || '');
            setImageNegativePrompt(response.negative_prompt || '');
            setImageAltText(response.alt_text || '');
            setImageCaption(response.caption || '');
            setImageNotice('Image recommendation updated from the current article context.');
            _context5.n = 4;
            break;
          case 3:
            _context5.p = 3;
            _t5 = _context5.v;
            setImageError((_t5 === null || _t5 === void 0 ? void 0 : _t5.message) || 'Failed to generate an image recommendation.');
          case 4:
            _context5.p = 4;
            setImageRecommendationLoading(false);
            return _context5.f(4);
          case 5:
            return _context5.a(2);
        }
      }, _callee5, null, [[1, 3, 4, 5]]);
    }));
    return function handleRecommendImage() {
      return _ref6.apply(this, arguments);
    };
  }();
  var handleGenerateImage = /*#__PURE__*/function () {
    var _ref7 = _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee6() {
      var _response$attachments, response, firstAttachment, _t6;
      return _regenerator().w(function (_context6) {
        while (1) switch (_context6.p = _context6.n) {
          case 0:
            setImageGenerateLoading(true);
            setImageError('');
            setImageNotice('');
            _context6.p = 1;
            _context6.n = 2;
            return apiFetch({
              path: 'dual-gpt/v1/images/generate',
              method: 'POST',
              data: buildImagePayload()
            });
          case 2:
            response = _context6.v;
            setImageResult(response);
            setImageNotice(response.stored_in_media_library ? 'Image generated and saved to the media library.' : 'Image generated successfully.');
            firstAttachment = (_response$attachments = response.attachments) === null || _response$attachments === void 0 ? void 0 : _response$attachments[0];
            if (firstAttachment !== null && firstAttachment !== void 0 && firstAttachment.attachment_id && imageSetFeatured) {
              editPost({
                featured_media: firstAttachment.attachment_id
              });
            }
            _context6.n = 4;
            break;
          case 3:
            _context6.p = 3;
            _t6 = _context6.v;
            setImageError((_t6 === null || _t6 === void 0 ? void 0 : _t6.message) || 'Failed to generate image.');
          case 4:
            _context6.p = 4;
            setImageGenerateLoading(false);
            return _context6.f(4);
          case 5:
            return _context6.a(2);
        }
      }, _callee6, null, [[1, 3, 4, 5]]);
    }));
    return function handleGenerateImage() {
      return _ref7.apply(this, arguments);
    };
  }();
  var insertGeneratedImage = function insertGeneratedImage() {
    var _imageResult$attachme;
    var firstAttachment = imageResult === null || imageResult === void 0 || (_imageResult$attachme = imageResult.attachments) === null || _imageResult$attachme === void 0 ? void 0 : _imageResult$attachme[0];
    if (!(firstAttachment !== null && firstAttachment !== void 0 && firstAttachment.attachment_id) || !(firstAttachment !== null && firstAttachment !== void 0 && firstAttachment.url)) {
      return;
    }
    var imageBlock = wp.blocks.createBlock('core/image', {
      id: firstAttachment.attachment_id,
      url: firstAttachment.url,
      alt: imageAltText,
      caption: imageCaption
    });
    insertBlocks([imageBlock]);
    createNotice('success', 'Generated image inserted into the post.', {
      type: 'snackbar'
    });
  };
  return wp.element.createElement(PluginSidebar, {
    name: "dual-gpt-sidebar",
    title: "Dual-GPT Authoring",
    icon: "admin-tools"
  }, wp.element.createElement(PanelBody, {
    title: "AI Images",
    initialOpen: true
  }, imageConfigLoading ? wp.element.createElement(Spinner, null) : null, imageConfigError ? wp.element.createElement(StatusMessage, {
    tone: "error",
    title: "Image settings unavailable"
  }, imageConfigError) : null, !imageConfigLoading && !imageConfigError ? wp.element.createElement(wp.element.Fragment, null, wp.element.createElement(SelectControl, {
    label: "Style Preset",
    value: imagePresetKey,
    options: Object.entries((imageConfig === null || imageConfig === void 0 ? void 0 : imageConfig.presets) || {}).map(function (_ref8) {
      var _ref9 = _slicedToArray(_ref8, 2),
        value = _ref9[0],
        preset = _ref9[1];
      return {
        label: preset.label || value,
        value: value
      };
    }),
    onChange: function onChange(value) {
      var _imageConfig$presets;
      setImagePresetKey(value);
      var preset = imageConfig === null || imageConfig === void 0 || (_imageConfig$presets = imageConfig.presets) === null || _imageConfig$presets === void 0 ? void 0 : _imageConfig$presets[value];
      if (preset !== null && preset !== void 0 && preset.aspect_ratio) {
        setImageAspectRatio(preset.aspect_ratio);
      }
    }
  }), wp.element.createElement(SelectControl, {
    label: "Image Provider",
    value: imageProvider,
    options: Object.entries((imageConfig === null || imageConfig === void 0 ? void 0 : imageConfig.provider_status) || {}).filter(function (_ref0) {
      var _ref1 = _slicedToArray(_ref0, 2),
        provider = _ref1[1];
      return (provider.supports || []).includes('image');
    }).map(function (_ref10) {
      var _ref11 = _slicedToArray(_ref10, 2),
        value = _ref11[0],
        provider = _ref11[1];
      return {
        label: "".concat(provider.label).concat(provider.configured ? '' : ' (not configured)'),
        value: value,
        disabled: !provider.enabled
      };
    }),
    onChange: setImageProvider
  }), wp.element.createElement(SelectControl, {
    label: "Aspect Ratio",
    value: imageAspectRatio,
    options: [{
      label: '16:9',
      value: '16:9'
    }, {
      label: '4:3',
      value: '4:3'
    }, {
      label: '3:4',
      value: '3:4'
    }, {
      label: '1:1',
      value: '1:1'
    }, {
      label: '9:16',
      value: '9:16'
    }],
    onChange: setImageAspectRatio
  }), wp.element.createElement(SelectControl, {
    label: "Image Size",
    value: imageSize,
    options: [{
      label: '2K',
      value: '2K'
    }, {
      label: '4K',
      value: '4K'
    }],
    onChange: setImageSize
  }), wp.element.createElement(TextControl, {
    label: "Additional Keywords",
    value: imageAdditionalKeywords,
    onChange: setImageAdditionalKeywords,
    placeholder: "Optional themes, objects, sectors, or motifs",
    help: "Comma-separated keywords to steer the image without rewriting the full prompt."
  }), wp.element.createElement(TextControl, {
    label: "Text In Image",
    value: imageTextInImage,
    onChange: setImageTextInImage,
    placeholder: "Optional exact text to render"
  }), wp.element.createElement(TextareaControl, {
    label: "Prompt",
    value: imagePrompt,
    onChange: setImagePrompt,
    placeholder: "Generate a recommendation first, or write your own prompt."
  }), wp.element.createElement(TextareaControl, {
    label: "Negative Prompt",
    value: imageNegativePrompt,
    onChange: setImageNegativePrompt,
    placeholder: "Optional exclusions"
  }), wp.element.createElement(TextControl, {
    label: "Alt Text",
    value: imageAltText,
    onChange: setImageAltText
  }), wp.element.createElement(TextControl, {
    label: "Caption",
    value: imageCaption,
    onChange: setImageCaption
  }), wp.element.createElement(ToggleControl, {
    label: "Editorial Accuracy / Google Search Grounding",
    checked: imageEditorialAccuracy,
    onChange: setImageEditorialAccuracy
  }), wp.element.createElement(ToggleControl, {
    label: "Save To Media Library",
    checked: imageStoreMedia,
    onChange: setImageStoreMedia
  }), wp.element.createElement(ToggleControl, {
    label: "Set As Featured Image",
    checked: imageSetFeatured,
    onChange: setImageSetFeatured
  }), wp.element.createElement("div", {
    className: "dual-gpt-button-row"
  }, wp.element.createElement(Button, {
    isSecondary: true,
    onClick: handleRecommendImage,
    disabled: imageRecommendationLoading || imageGenerateLoading
  }, imageRecommendationLoading ? wp.element.createElement(Spinner, null) : 'Recommend'), wp.element.createElement(Button, {
    isPrimary: true,
    onClick: handleGenerateImage,
    disabled: imageGenerateLoading || imageRecommendationLoading || !imagePrompt.trim()
  }, imageGenerateLoading ? wp.element.createElement(Spinner, null) : 'Generate')), imageError ? wp.element.createElement(StatusMessage, {
    tone: "error",
    title: "Image generation failed"
  }, imageError) : null, imageNotice ? wp.element.createElement(StatusMessage, {
    tone: "success",
    title: "Image workflow"
  }, imageNotice) : null, imageRecommendation !== null && imageRecommendation !== void 0 && imageRecommendation.rationale ? wp.element.createElement(Notice, {
    status: "info",
    isDismissible: false
  }, imageRecommendation.rationale) : null, imageResult !== null && imageResult !== void 0 && (_imageResult$attachme2 = imageResult.attachments) !== null && _imageResult$attachme2 !== void 0 && (_imageResult$attachme2 = _imageResult$attachme2[0]) !== null && _imageResult$attachme2 !== void 0 && _imageResult$attachme2.url ? wp.element.createElement("div", {
    className: "dual-gpt-image-preview"
  }, wp.element.createElement("img", {
    src: imageResult.attachments[0].url,
    alt: imageAltText || 'Generated image preview'
  }), wp.element.createElement("div", {
    className: "dual-gpt-button-row"
  }, wp.element.createElement(Button, {
    isSecondary: true,
    onClick: insertGeneratedImage
  }, "Insert Inline"))) : null) : null), wp.element.createElement(PanelBody, {
    title: "Research Pane",
    initialOpen: false
  }, wp.element.createElement(TextareaControl, {
    label: "Research Prompt",
    value: researchPrompt,
    onChange: function onChange(value) {
      setResearchPrompt(value);
      if (researchError) setResearchError('');
    },
    placeholder: "Enter your research query..."
  }), wp.element.createElement(Button, {
    isPrimary: true,
    onClick: handleResearchSubmit,
    disabled: researchLoading || !researchPrompt.trim()
  }, researchLoading ? wp.element.createElement(Spinner, null) : 'Research'), researchError ? wp.element.createElement(StatusMessage, {
    tone: "error",
    title: "Research error"
  }, researchError) : null, researchResults && !researchError ? wp.element.createElement(StatusMessage, {
    tone: "success",
    title: "Research"
  }, researchResults) : null), wp.element.createElement(PanelBody, {
    title: "Author Agent",
    initialOpen: false
  }, wp.element.createElement("label", {
    style: {
      display: 'block',
      marginBottom: '6px',
      fontWeight: 600
    }
  }, "Mode"), wp.element.createElement("select", {
    value: authorMode,
    onChange: function onChange(event) {
      return setAuthorMode(event.target.value);
    },
    style: {
      width: '100%',
      marginBottom: '12px'
    }
  }, wp.element.createElement("option", {
    value: "draft"
  }, "Draft"), wp.element.createElement("option", {
    value: "abstract"
  }, "Abstract"), wp.element.createElement("option", {
    value: "enrichment"
  }, "Enrichment")), wp.element.createElement(TextareaControl, {
    label: "Framework Brief ID",
    value: frameworkBriefId,
    onChange: function onChange(value) {
      return setFrameworkBriefId(value);
    },
    placeholder: "FG brief ID (required for draft)"
  }), wp.element.createElement(TextareaControl, {
    label: "Planner Session ID",
    value: plannerSessionId,
    onChange: function onChange(value) {
      return setPlannerSessionId(value);
    },
    placeholder: "Editorial Planner session ID (required for draft)"
  }), wp.element.createElement(TextareaControl, {
    label: "Author Instructions (optional)",
    value: authorInstructions,
    onChange: function onChange(value) {
      return setAuthorInstructions(value);
    },
    placeholder: "Optional constraints or notes"
  }), wp.element.createElement(PanelBody, {
    title: "Core Settings",
    initialOpen: false
  }, wp.element.createElement(TextareaControl, {
    label: "Industry Focus",
    value: authorCoreSettings.industry_focus,
    onChange: function onChange(value) {
      return setAuthorCoreSettings(_objectSpread(_objectSpread({}, authorCoreSettings), {}, {
        industry_focus: value
      }));
    }
  }), wp.element.createElement(TextareaControl, {
    label: "Audience Tier",
    value: authorCoreSettings.audience_tier,
    onChange: function onChange(value) {
      return setAuthorCoreSettings(_objectSpread(_objectSpread({}, authorCoreSettings), {}, {
        audience_tier: value
      }));
    }
  }), wp.element.createElement(TextareaControl, {
    label: "Risk Tolerance",
    value: authorCoreSettings.risk_tolerance,
    onChange: function onChange(value) {
      return setAuthorCoreSettings(_objectSpread(_objectSpread({}, authorCoreSettings), {}, {
        risk_tolerance: value
      }));
    }
  }), wp.element.createElement(TextareaControl, {
    label: "Brand Profile",
    value: authorCoreSettings.brand_profile,
    onChange: function onChange(value) {
      return setAuthorCoreSettings(_objectSpread(_objectSpread({}, authorCoreSettings), {}, {
        brand_profile: value
      }));
    }
  })), wp.element.createElement("div", {
    className: "dual-gpt-button-row"
  }, wp.element.createElement(Button, {
    isPrimary: true,
    onClick: handleAuthorSubmit,
    disabled: authorLoading
  }, authorLoading ? wp.element.createElement(Spinner, null) : 'Run Author Agent'), wp.element.createElement(Button, {
    isSecondary: true,
    onClick: insertBlocksFromAuthor,
    disabled: !authorBlocks.length || !!authorError
  }, "Insert Blocks")), authorError ? wp.element.createElement(StatusMessage, {
    tone: "error",
    title: "Author error"
  }, authorError) : null, authorWarnings.length > 0 ? wp.element.createElement(StatusMessage, {
    tone: "warning",
    title: "Warnings"
  }, wp.element.createElement("ul", null, authorWarnings.map(function (warning, index) {
    return wp.element.createElement("li", {
      key: index
    }, warning);
  }))) : null, authorValidationErrors.length > 0 ? wp.element.createElement(StatusMessage, {
    tone: "error",
    title: "Validation Errors"
  }, wp.element.createElement("ul", null, authorValidationErrors.map(function (error, index) {
    return wp.element.createElement("li", {
      key: index
    }, error);
  }))) : null, authorResults && !authorError ? wp.element.createElement(StatusMessage, {
    tone: "success",
    title: "Author"
  }, authorResults) : null, authorAbstract ? wp.element.createElement("div", {
    className: "dual-gpt-results"
  }, wp.element.createElement("strong", null, "Abstract Output:"), wp.element.createElement("pre", {
    style: {
      whiteSpace: 'pre-wrap'
    }
  }, JSON.stringify(authorAbstract, null, 2))) : null));
};
registerPlugin('dual-gpt-sidebar', {
  render: DualGPTSidebar,
  icon: 'admin-tools'
});

//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoic2lkZWJhci5idWlsZC5qcyIsIm5hbWVzIjpbImUiLCJ0IiwiciIsIlN5bWJvbCIsIm4iLCJpdGVyYXRvciIsIm8iLCJ0b1N0cmluZ1RhZyIsImkiLCJjIiwicHJvdG90eXBlIiwiR2VuZXJhdG9yIiwidSIsIk9iamVjdCIsImNyZWF0ZSIsIl9yZWdlbmVyYXRvckRlZmluZTIiLCJmIiwicCIsInkiLCJHIiwidiIsImEiLCJkIiwiYmluZCIsImxlbmd0aCIsImwiLCJUeXBlRXJyb3IiLCJjYWxsIiwiZG9uZSIsInZhbHVlIiwicmV0dXJuIiwiR2VuZXJhdG9yRnVuY3Rpb24iLCJHZW5lcmF0b3JGdW5jdGlvblByb3RvdHlwZSIsImdldFByb3RvdHlwZU9mIiwic2V0UHJvdG90eXBlT2YiLCJfX3Byb3RvX18iLCJkaXNwbGF5TmFtZSIsIl9yZWdlbmVyYXRvciIsInciLCJtIiwiZGVmaW5lUHJvcGVydHkiLCJfcmVnZW5lcmF0b3JEZWZpbmUiLCJfaW52b2tlIiwiZW51bWVyYWJsZSIsImNvbmZpZ3VyYWJsZSIsIndyaXRhYmxlIiwiYXN5bmNHZW5lcmF0b3JTdGVwIiwiUHJvbWlzZSIsInJlc29sdmUiLCJ0aGVuIiwiX2FzeW5jVG9HZW5lcmF0b3IiLCJhcmd1bWVudHMiLCJhcHBseSIsIl9uZXh0IiwiX3Rocm93IiwiX3NsaWNlZFRvQXJyYXkiLCJfYXJyYXlXaXRoSG9sZXMiLCJfaXRlcmFibGVUb0FycmF5TGltaXQiLCJfdW5zdXBwb3J0ZWRJdGVyYWJsZVRvQXJyYXkiLCJfbm9uSXRlcmFibGVSZXN0IiwiX2FycmF5TGlrZVRvQXJyYXkiLCJ0b1N0cmluZyIsInNsaWNlIiwiY29uc3RydWN0b3IiLCJuYW1lIiwiQXJyYXkiLCJmcm9tIiwidGVzdCIsIm5leHQiLCJwdXNoIiwiaXNBcnJheSIsInJlZ2lzdGVyUGx1Z2luIiwid3AiLCJwbHVnaW5zIiwiUGx1Z2luU2lkZWJhciIsImVkaXRQb3N0IiwiX3dwJGNvbXBvbmVudHMiLCJjb21wb25lbnRzIiwiUGFuZWxCb2R5IiwiVGV4dGFyZWFDb250cm9sIiwiVGV4dENvbnRyb2wiLCJCdXR0b24iLCJTcGlubmVyIiwiVG9nZ2xlQ29udHJvbCIsIlNlbGVjdENvbnRyb2wiLCJOb3RpY2UiLCJfd3AkZWxlbWVudCIsImVsZW1lbnQiLCJ1c2VTdGF0ZSIsInVzZUVmZmVjdCIsIl93cCRkYXRhIiwiZGF0YSIsInVzZVNlbGVjdCIsInVzZURpc3BhdGNoIiwiX3dwIiwiYXBpRmV0Y2giLCJTdGF0dXNNZXNzYWdlIiwiX3JlZiIsIl9yZWYkdG9uZSIsInRvbmUiLCJ0aXRsZSIsImNoaWxkcmVuIiwiY3JlYXRlRWxlbWVudCIsImNsYXNzTmFtZSIsImNvbmNhdCIsIkR1YWxHUFRTaWRlYmFyIiwiX2R1YWxHcHREYXRhIiwiX2R1YWxHcHREYXRhMiIsIl9kdWFsR3B0RGF0YTMiLCJfZHVhbEdwdERhdGE0IiwiX2ltYWdlUmVzdWx0JGF0dGFjaG1lMiIsIl91c2VTdGF0ZSIsIl91c2VTdGF0ZTIiLCJyZXNlYXJjaFByb21wdCIsInNldFJlc2VhcmNoUHJvbXB0IiwiX3VzZVN0YXRlMyIsIl91c2VTdGF0ZTQiLCJhdXRob3JNb2RlIiwic2V0QXV0aG9yTW9kZSIsIl91c2VTdGF0ZTUiLCJfdXNlU3RhdGU2IiwiZnJhbWV3b3JrQnJpZWZJZCIsInNldEZyYW1ld29ya0JyaWVmSWQiLCJfdXNlU3RhdGU3IiwiX3VzZVN0YXRlOCIsInBsYW5uZXJTZXNzaW9uSWQiLCJzZXRQbGFubmVyU2Vzc2lvbklkIiwiX3VzZVN0YXRlOSIsIl91c2VTdGF0ZTAiLCJhdXRob3JJbnN0cnVjdGlvbnMiLCJzZXRBdXRob3JJbnN0cnVjdGlvbnMiLCJfdXNlU3RhdGUxIiwiaW5kdXN0cnlfZm9jdXMiLCJkdWFsR3B0RGF0YSIsImNvcmVTZXR0aW5ncyIsImF1ZGllbmNlX3RpZXIiLCJyaXNrX3RvbGVyYW5jZSIsImJyYW5kX3Byb2ZpbGUiLCJfdXNlU3RhdGUxMCIsImF1dGhvckNvcmVTZXR0aW5ncyIsInNldEF1dGhvckNvcmVTZXR0aW5ncyIsIl91c2VTdGF0ZTExIiwiX3VzZVN0YXRlMTIiLCJyZXNlYXJjaExvYWRpbmciLCJzZXRSZXNlYXJjaExvYWRpbmciLCJfdXNlU3RhdGUxMyIsIl91c2VTdGF0ZTE0IiwiYXV0aG9yTG9hZGluZyIsInNldEF1dGhvckxvYWRpbmciLCJfdXNlU3RhdGUxNSIsIl91c2VTdGF0ZTE2IiwicmVzZWFyY2hSZXN1bHRzIiwic2V0UmVzZWFyY2hSZXN1bHRzIiwiX3VzZVN0YXRlMTciLCJfdXNlU3RhdGUxOCIsImF1dGhvclJlc3VsdHMiLCJzZXRBdXRob3JSZXN1bHRzIiwiX3VzZVN0YXRlMTkiLCJfdXNlU3RhdGUyMCIsInJlc2VhcmNoRXJyb3IiLCJzZXRSZXNlYXJjaEVycm9yIiwiX3VzZVN0YXRlMjEiLCJfdXNlU3RhdGUyMiIsImF1dGhvckVycm9yIiwic2V0QXV0aG9yRXJyb3IiLCJfdXNlU3RhdGUyMyIsIl91c2VTdGF0ZTI0IiwicmVzZWFyY2hKb2JJZCIsInNldFJlc2VhcmNoSm9iSWQiLCJfdXNlU3RhdGUyNSIsIl91c2VTdGF0ZTI2IiwiYXV0aG9ySm9iSWQiLCJzZXRBdXRob3JKb2JJZCIsIl91c2VTdGF0ZTI3IiwiX3VzZVN0YXRlMjgiLCJhdXRob3JCbG9ja3MiLCJzZXRBdXRob3JCbG9ja3MiLCJfdXNlU3RhdGUyOSIsIl91c2VTdGF0ZTMwIiwiYXV0aG9yQWJzdHJhY3QiLCJzZXRBdXRob3JBYnN0cmFjdCIsIl91c2VTdGF0ZTMxIiwiX3VzZVN0YXRlMzIiLCJhdXRob3JXYXJuaW5ncyIsInNldEF1dGhvcldhcm5pbmdzIiwiX3VzZVN0YXRlMzMiLCJfdXNlU3RhdGUzNCIsImF1dGhvclZhbGlkYXRpb25FcnJvcnMiLCJzZXRBdXRob3JWYWxpZGF0aW9uRXJyb3JzIiwiX3VzZVN0YXRlMzUiLCJfdXNlU3RhdGUzNiIsImltYWdlQ29uZmlnIiwic2V0SW1hZ2VDb25maWciLCJfdXNlU3RhdGUzNyIsIl91c2VTdGF0ZTM4IiwiaW1hZ2VDb25maWdMb2FkaW5nIiwic2V0SW1hZ2VDb25maWdMb2FkaW5nIiwiX3VzZVN0YXRlMzkiLCJfdXNlU3RhdGU0MCIsImltYWdlQ29uZmlnRXJyb3IiLCJzZXRJbWFnZUNvbmZpZ0Vycm9yIiwiX3VzZVN0YXRlNDEiLCJfdXNlU3RhdGU0MiIsImltYWdlUmVjb21tZW5kYXRpb25Mb2FkaW5nIiwic2V0SW1hZ2VSZWNvbW1lbmRhdGlvbkxvYWRpbmciLCJfdXNlU3RhdGU0MyIsIl91c2VTdGF0ZTQ0IiwiaW1hZ2VHZW5lcmF0ZUxvYWRpbmciLCJzZXRJbWFnZUdlbmVyYXRlTG9hZGluZyIsIl91c2VTdGF0ZTQ1IiwiX3VzZVN0YXRlNDYiLCJpbWFnZUVycm9yIiwic2V0SW1hZ2VFcnJvciIsIl91c2VTdGF0ZTQ3IiwiX3VzZVN0YXRlNDgiLCJpbWFnZU5vdGljZSIsInNldEltYWdlTm90aWNlIiwiX3VzZVN0YXRlNDkiLCJfdXNlU3RhdGU1MCIsImltYWdlUmVjb21tZW5kYXRpb24iLCJzZXRJbWFnZVJlY29tbWVuZGF0aW9uIiwiX3VzZVN0YXRlNTEiLCJfdXNlU3RhdGU1MiIsImltYWdlUmVzdWx0Iiwic2V0SW1hZ2VSZXN1bHQiLCJfdXNlU3RhdGU1MyIsIl91c2VTdGF0ZTU0IiwiaW1hZ2VQcm9tcHQiLCJzZXRJbWFnZVByb21wdCIsIl91c2VTdGF0ZTU1IiwiX3VzZVN0YXRlNTYiLCJpbWFnZU5lZ2F0aXZlUHJvbXB0Iiwic2V0SW1hZ2VOZWdhdGl2ZVByb21wdCIsIl91c2VTdGF0ZTU3IiwiX3VzZVN0YXRlNTgiLCJpbWFnZUFsdFRleHQiLCJzZXRJbWFnZUFsdFRleHQiLCJfdXNlU3RhdGU1OSIsIl91c2VTdGF0ZTYwIiwiaW1hZ2VDYXB0aW9uIiwic2V0SW1hZ2VDYXB0aW9uIiwiX3VzZVN0YXRlNjEiLCJfdXNlU3RhdGU2MiIsImltYWdlVGV4dEluSW1hZ2UiLCJzZXRJbWFnZVRleHRJbkltYWdlIiwiX3VzZVN0YXRlNjMiLCJfdXNlU3RhdGU2NCIsImltYWdlRWRpdG9yaWFsQWNjdXJhY3kiLCJzZXRJbWFnZUVkaXRvcmlhbEFjY3VyYWN5IiwiX3VzZVN0YXRlNjUiLCJfdXNlU3RhdGU2NiIsImltYWdlU2V0RmVhdHVyZWQiLCJzZXRJbWFnZVNldEZlYXR1cmVkIiwiX3VzZVN0YXRlNjciLCJfdXNlU3RhdGU2OCIsImltYWdlU3RvcmVNZWRpYSIsInNldEltYWdlU3RvcmVNZWRpYSIsIl91c2VTdGF0ZTY5IiwiX3VzZVN0YXRlNzAiLCJpbWFnZUFzcGVjdFJhdGlvIiwic2V0SW1hZ2VBc3BlY3RSYXRpbyIsIl91c2VTdGF0ZTcxIiwiX3VzZVN0YXRlNzIiLCJpbWFnZVNpemUiLCJzZXRJbWFnZVNpemUiLCJfdXNlU3RhdGU3MyIsIl91c2VTdGF0ZTc0IiwiaW1hZ2VQcm92aWRlciIsInNldEltYWdlUHJvdmlkZXIiLCJfdXNlU3RhdGU3NSIsIl91c2VTdGF0ZTc2IiwiaW1hZ2VQcmVzZXRLZXkiLCJzZXRJbWFnZVByZXNldEtleSIsIl91c2VTdGF0ZTc3IiwiX3VzZVN0YXRlNzgiLCJpbWFnZUFkZGl0aW9uYWxLZXl3b3JkcyIsInNldEltYWdlQWRkaXRpb25hbEtleXdvcmRzIiwiX3VzZURpc3BhdGNoIiwiaW5zZXJ0QmxvY2tzIiwiX3VzZURpc3BhdGNoMiIsIl91c2VEaXNwYXRjaDMiLCJjcmVhdGVOb3RpY2UiLCJwb3N0SWQiLCJzZWxlY3QiLCJnZXRDdXJyZW50UG9zdElkIiwiZHJhZnRDb250ZW50IiwiZ2V0RWRpdGVkUG9zdENvbnRlbnQiLCJwb3N0VGl0bGUiLCJnZXRFZGl0ZWRQb3N0QXR0cmlidXRlIiwicG9zdEV4Y2VycHQiLCJsb2FkSW1hZ2VDb25maWciLCJfcmVmMiIsIl9jYWxsZWUiLCJfcmVzcG9uc2UkaG91c2Vfc3R5bGUiLCJfcmVzcG9uc2Ukd29ya2Zsb3ciLCJfcmVzcG9uc2Ukd29ya2Zsb3cyIiwicmVzcG9uc2UiLCJfdCIsIl9jb250ZXh0IiwicGF0aCIsIm1ldGhvZCIsImltYWdlX3Byb3ZpZGVyIiwiZGVmYXVsdF9wcmVzZXRfa2V5IiwiaG91c2Vfc3R5bGUiLCJhc3BlY3RfcmF0aW8iLCJ3b3JrZmxvdyIsImF1dG9fc3RvcmVfbWVkaWEiLCJhbGxvd19mZWF0dXJlZF9pbWFnZV9yZXBsYWNlIiwibWVzc2FnZSIsImhhbmRsZVJlc2VhcmNoU3VibWl0IiwiX3JlZjMiLCJfY2FsbGVlMiIsInNlc3Npb25SZXNwb25zZSIsImpvYlJlc3BvbnNlIiwiZXJyb3JNZXNzYWdlIiwiX3QyIiwiX2NvbnRleHQyIiwidHJpbSIsInJvbGUiLCJEYXRlIiwidG9Mb2NhbGVTdHJpbmciLCJzZXNzaW9uX2lkIiwicHJvbXB0IiwibW9kZWwiLCJqb2JfaWQiLCJwb2xsSm9iU3RhdHVzIiwiY29kZSIsInR5cGUiLCJoYW5kbGVBdXRob3JTdWJtaXQiLCJfcmVmNCIsIl9jYWxsZWUzIiwicGF5bG9hZCIsIl9yZXNwb25zZSRvdXRwdXQiLCJfcmVzcG9uc2Ukb3V0cHV0MiIsIl9yZXNwb25zZSRvdXRwdXQzIiwiX3QzIiwiX2NvbnRleHQzIiwibW9kZSIsImZyYW1ld29ya19icmllZl9pZCIsInVuZGVmaW5lZCIsInBsYW5uZXJfc2Vzc2lvbl9pZCIsImRyYWZ0X2NvbnRlbnQiLCJpbnN0cnVjdGlvbnMiLCJjb3JlX3NldHRpbmdzIiwid2FybmluZ3MiLCJ2YWxpZGF0aW9uX2Vycm9ycyIsIm91dHB1dCIsImJsb2NrcyIsImFic3RyYWN0IiwiX3JlZjUiLCJfY2FsbGVlNCIsImpvYklkIiwiZXJyb3JNc2ciLCJfZXJyb3JNc2ciLCJfdDQiLCJfY29udGV4dDQiLCJzdGF0dXMiLCJlcnJvcl9tZXNzYWdlIiwic2V0VGltZW91dCIsIl94IiwiX3gyIiwiaW5zZXJ0QmxvY2tzRnJvbUF1dGhvciIsImVzY2FwZUh0bWwiLCJTdHJpbmciLCJyZXBsYWNlIiwiYnVpbGRQdWxscXVvdGVNZXRhU3BhbiIsIm1ldGEiLCJfdHlwZW9mIiwiYXR0cmlidXRlcyIsInNvdXJjZV9hdXRob3IiLCJwdWJsaWNhdGlvbiIsIm9yZ2FuaXNhdGlvbiIsImRhdGUiLCJjaXRhdGlvbl9yZWZfaWQiLCJqb2luIiwibWFwIiwiYmxvY2siLCJjcmVhdGVCbG9jayIsImxldmVsIiwiY29udGVudCIsImxpc3RJdGVtcyIsIml0ZW1zIiwiaXRlbSIsImxpc3RUYWciLCJvcmRlcmVkIiwidmFsdWVzIiwicHVsbHF1b3RlTWV0YSIsIm1ldGFkYXRhIiwiY2l0YXRpb24iLCJjaXRlIiwiYnVpbGRJbWFnZVBheWxvYWQiLCJwb3N0X2lkIiwic3VtbWFyeSIsIm5lZ2F0aXZlX3Byb21wdCIsImFsdF90ZXh0IiwiY2FwdGlvbiIsInByb3ZpZGVyIiwicHJlc2V0X2tleSIsImtleXdvcmRzIiwidGV4dF9pbl9pbWFnZSIsImVkaXRvcmlhbF9hY2N1cmFjeSIsInN0b3JlX2luX21lZGlhX2xpYnJhcnkiLCJzZXRfZmVhdHVyZWRfaW1hZ2UiLCJpbWFnZV9zaXplIiwiaGFuZGxlUmVjb21tZW5kSW1hZ2UiLCJfcmVmNiIsIl9jYWxsZWU1IiwiX3Q1IiwiX2NvbnRleHQ1IiwiaGFuZGxlR2VuZXJhdGVJbWFnZSIsIl9yZWY3IiwiX2NhbGxlZTYiLCJfcmVzcG9uc2UkYXR0YWNobWVudHMiLCJmaXJzdEF0dGFjaG1lbnQiLCJfdDYiLCJfY29udGV4dDYiLCJzdG9yZWRfaW5fbWVkaWFfbGlicmFyeSIsImF0dGFjaG1lbnRzIiwiYXR0YWNobWVudF9pZCIsImZlYXR1cmVkX21lZGlhIiwiaW5zZXJ0R2VuZXJhdGVkSW1hZ2UiLCJfaW1hZ2VSZXN1bHQkYXR0YWNobWUiLCJ1cmwiLCJpbWFnZUJsb2NrIiwiaWQiLCJhbHQiLCJpY29uIiwiaW5pdGlhbE9wZW4iLCJGcmFnbWVudCIsImxhYmVsIiwib3B0aW9ucyIsImVudHJpZXMiLCJwcmVzZXRzIiwiX3JlZjgiLCJfcmVmOSIsInByZXNldCIsIm9uQ2hhbmdlIiwiX2ltYWdlQ29uZmlnJHByZXNldHMiLCJwcm92aWRlcl9zdGF0dXMiLCJmaWx0ZXIiLCJfcmVmMCIsIl9yZWYxIiwic3VwcG9ydHMiLCJpbmNsdWRlcyIsIl9yZWYxMCIsIl9yZWYxMSIsImNvbmZpZ3VyZWQiLCJkaXNhYmxlZCIsImVuYWJsZWQiLCJwbGFjZWhvbGRlciIsImhlbHAiLCJjaGVja2VkIiwiaXNTZWNvbmRhcnkiLCJvbkNsaWNrIiwiaXNQcmltYXJ5IiwicmF0aW9uYWxlIiwiaXNEaXNtaXNzaWJsZSIsInNyYyIsInN0eWxlIiwiZGlzcGxheSIsIm1hcmdpbkJvdHRvbSIsImZvbnRXZWlnaHQiLCJldmVudCIsInRhcmdldCIsIndpZHRoIiwiX29iamVjdFNwcmVhZCIsIndhcm5pbmciLCJpbmRleCIsImtleSIsImVycm9yIiwid2hpdGVTcGFjZSIsIkpTT04iLCJzdHJpbmdpZnkiLCJyZW5kZXIiXSwic291cmNlcyI6WyJzaWRlYmFyLmpzIl0sInNvdXJjZXNDb250ZW50IjpbIi8qKlxuICogRHVhbC1HUFQgR3V0ZW5iZXJnIFNpZGViYXJcbiAqL1xuXG5jb25zdCB7IHJlZ2lzdGVyUGx1Z2luIH0gPSB3cC5wbHVnaW5zO1xuY29uc3QgeyBQbHVnaW5TaWRlYmFyIH0gPSB3cC5lZGl0UG9zdDtcbmNvbnN0IHtcbiAgICBQYW5lbEJvZHksXG4gICAgVGV4dGFyZWFDb250cm9sLFxuICAgIFRleHRDb250cm9sLFxuICAgIEJ1dHRvbixcbiAgICBTcGlubmVyLFxuICAgIFRvZ2dsZUNvbnRyb2wsXG4gICAgU2VsZWN0Q29udHJvbCxcbiAgICBOb3RpY2UsXG59ID0gd3AuY29tcG9uZW50cztcbmNvbnN0IHsgdXNlU3RhdGUsIHVzZUVmZmVjdCB9ID0gd3AuZWxlbWVudDtcbmNvbnN0IHsgdXNlU2VsZWN0LCB1c2VEaXNwYXRjaCB9ID0gd3AuZGF0YTtcbmNvbnN0IHsgYXBpRmV0Y2ggfSA9IHdwO1xuXG5jb25zdCBTdGF0dXNNZXNzYWdlID0gKHsgdG9uZSA9ICdpbmZvJywgdGl0bGUsIGNoaWxkcmVuIH0pID0+IChcbiAgICA8ZGl2IGNsYXNzTmFtZT17YGR1YWwtZ3B0LW1lc3NhZ2UgZHVhbC1ncHQtbWVzc2FnZS0ke3RvbmV9YH0+XG4gICAgICAgIHt0aXRsZSA/IDxzdHJvbmc+e3RpdGxlfTwvc3Ryb25nPiA6IG51bGx9XG4gICAgICAgIHtjaGlsZHJlbiA/IDxkaXY+e2NoaWxkcmVufTwvZGl2PiA6IG51bGx9XG4gICAgPC9kaXY+XG4pO1xuXG5jb25zdCBEdWFsR1BUU2lkZWJhciA9ICgpID0+IHtcbiAgICBjb25zdCBbcmVzZWFyY2hQcm9tcHQsIHNldFJlc2VhcmNoUHJvbXB0XSA9IHVzZVN0YXRlKCcnKTtcbiAgICBjb25zdCBbYXV0aG9yTW9kZSwgc2V0QXV0aG9yTW9kZV0gPSB1c2VTdGF0ZSgnZHJhZnQnKTtcbiAgICBjb25zdCBbZnJhbWV3b3JrQnJpZWZJZCwgc2V0RnJhbWV3b3JrQnJpZWZJZF0gPSB1c2VTdGF0ZSgnJyk7XG4gICAgY29uc3QgW3BsYW5uZXJTZXNzaW9uSWQsIHNldFBsYW5uZXJTZXNzaW9uSWRdID0gdXNlU3RhdGUoJycpO1xuICAgIGNvbnN0IFthdXRob3JJbnN0cnVjdGlvbnMsIHNldEF1dGhvckluc3RydWN0aW9uc10gPSB1c2VTdGF0ZSgnJyk7XG4gICAgY29uc3QgW2F1dGhvckNvcmVTZXR0aW5ncywgc2V0QXV0aG9yQ29yZVNldHRpbmdzXSA9IHVzZVN0YXRlKHtcbiAgICAgICAgaW5kdXN0cnlfZm9jdXM6IGR1YWxHcHREYXRhPy5jb3JlU2V0dGluZ3M/LmluZHVzdHJ5X2ZvY3VzIHx8ICdHZW5lcmFsJyxcbiAgICAgICAgYXVkaWVuY2VfdGllcjogZHVhbEdwdERhdGE/LmNvcmVTZXR0aW5ncz8uYXVkaWVuY2VfdGllciB8fCAnR2VuZXJhbCcsXG4gICAgICAgIHJpc2tfdG9sZXJhbmNlOiBkdWFsR3B0RGF0YT8uY29yZVNldHRpbmdzPy5yaXNrX3RvbGVyYW5jZSB8fCAnTW9kZXJhdGUnLFxuICAgICAgICBicmFuZF9wcm9maWxlOiBkdWFsR3B0RGF0YT8uY29yZVNldHRpbmdzPy5icmFuZF9wcm9maWxlIHx8ICdCcmFuZCBBIChGU0kpJyxcbiAgICB9KTtcbiAgICBjb25zdCBbcmVzZWFyY2hMb2FkaW5nLCBzZXRSZXNlYXJjaExvYWRpbmddID0gdXNlU3RhdGUoZmFsc2UpO1xuICAgIGNvbnN0IFthdXRob3JMb2FkaW5nLCBzZXRBdXRob3JMb2FkaW5nXSA9IHVzZVN0YXRlKGZhbHNlKTtcbiAgICBjb25zdCBbcmVzZWFyY2hSZXN1bHRzLCBzZXRSZXNlYXJjaFJlc3VsdHNdID0gdXNlU3RhdGUoJycpO1xuICAgIGNvbnN0IFthdXRob3JSZXN1bHRzLCBzZXRBdXRob3JSZXN1bHRzXSA9IHVzZVN0YXRlKCcnKTtcbiAgICBjb25zdCBbcmVzZWFyY2hFcnJvciwgc2V0UmVzZWFyY2hFcnJvcl0gPSB1c2VTdGF0ZSgnJyk7XG4gICAgY29uc3QgW2F1dGhvckVycm9yLCBzZXRBdXRob3JFcnJvcl0gPSB1c2VTdGF0ZSgnJyk7XG4gICAgY29uc3QgW3Jlc2VhcmNoSm9iSWQsIHNldFJlc2VhcmNoSm9iSWRdID0gdXNlU3RhdGUobnVsbCk7XG4gICAgY29uc3QgW2F1dGhvckpvYklkLCBzZXRBdXRob3JKb2JJZF0gPSB1c2VTdGF0ZShudWxsKTtcbiAgICBjb25zdCBbYXV0aG9yQmxvY2tzLCBzZXRBdXRob3JCbG9ja3NdID0gdXNlU3RhdGUoW10pO1xuICAgIGNvbnN0IFthdXRob3JBYnN0cmFjdCwgc2V0QXV0aG9yQWJzdHJhY3RdID0gdXNlU3RhdGUobnVsbCk7XG4gICAgY29uc3QgW2F1dGhvcldhcm5pbmdzLCBzZXRBdXRob3JXYXJuaW5nc10gPSB1c2VTdGF0ZShbXSk7XG4gICAgY29uc3QgW2F1dGhvclZhbGlkYXRpb25FcnJvcnMsIHNldEF1dGhvclZhbGlkYXRpb25FcnJvcnNdID0gdXNlU3RhdGUoW10pO1xuXG4gICAgY29uc3QgW2ltYWdlQ29uZmlnLCBzZXRJbWFnZUNvbmZpZ10gPSB1c2VTdGF0ZShudWxsKTtcbiAgICBjb25zdCBbaW1hZ2VDb25maWdMb2FkaW5nLCBzZXRJbWFnZUNvbmZpZ0xvYWRpbmddID0gdXNlU3RhdGUodHJ1ZSk7XG4gICAgY29uc3QgW2ltYWdlQ29uZmlnRXJyb3IsIHNldEltYWdlQ29uZmlnRXJyb3JdID0gdXNlU3RhdGUoJycpO1xuICAgIGNvbnN0IFtpbWFnZVJlY29tbWVuZGF0aW9uTG9hZGluZywgc2V0SW1hZ2VSZWNvbW1lbmRhdGlvbkxvYWRpbmddID0gdXNlU3RhdGUoZmFsc2UpO1xuICAgIGNvbnN0IFtpbWFnZUdlbmVyYXRlTG9hZGluZywgc2V0SW1hZ2VHZW5lcmF0ZUxvYWRpbmddID0gdXNlU3RhdGUoZmFsc2UpO1xuICAgIGNvbnN0IFtpbWFnZUVycm9yLCBzZXRJbWFnZUVycm9yXSA9IHVzZVN0YXRlKCcnKTtcbiAgICBjb25zdCBbaW1hZ2VOb3RpY2UsIHNldEltYWdlTm90aWNlXSA9IHVzZVN0YXRlKCcnKTtcbiAgICBjb25zdCBbaW1hZ2VSZWNvbW1lbmRhdGlvbiwgc2V0SW1hZ2VSZWNvbW1lbmRhdGlvbl0gPSB1c2VTdGF0ZShudWxsKTtcbiAgICBjb25zdCBbaW1hZ2VSZXN1bHQsIHNldEltYWdlUmVzdWx0XSA9IHVzZVN0YXRlKG51bGwpO1xuICAgIGNvbnN0IFtpbWFnZVByb21wdCwgc2V0SW1hZ2VQcm9tcHRdID0gdXNlU3RhdGUoJycpO1xuICAgIGNvbnN0IFtpbWFnZU5lZ2F0aXZlUHJvbXB0LCBzZXRJbWFnZU5lZ2F0aXZlUHJvbXB0XSA9IHVzZVN0YXRlKCcnKTtcbiAgICBjb25zdCBbaW1hZ2VBbHRUZXh0LCBzZXRJbWFnZUFsdFRleHRdID0gdXNlU3RhdGUoJycpO1xuICAgIGNvbnN0IFtpbWFnZUNhcHRpb24sIHNldEltYWdlQ2FwdGlvbl0gPSB1c2VTdGF0ZSgnJyk7XG4gICAgY29uc3QgW2ltYWdlVGV4dEluSW1hZ2UsIHNldEltYWdlVGV4dEluSW1hZ2VdID0gdXNlU3RhdGUoJycpO1xuICAgIGNvbnN0IFtpbWFnZUVkaXRvcmlhbEFjY3VyYWN5LCBzZXRJbWFnZUVkaXRvcmlhbEFjY3VyYWN5XSA9IHVzZVN0YXRlKGZhbHNlKTtcbiAgICBjb25zdCBbaW1hZ2VTZXRGZWF0dXJlZCwgc2V0SW1hZ2VTZXRGZWF0dXJlZF0gPSB1c2VTdGF0ZSh0cnVlKTtcbiAgICBjb25zdCBbaW1hZ2VTdG9yZU1lZGlhLCBzZXRJbWFnZVN0b3JlTWVkaWFdID0gdXNlU3RhdGUodHJ1ZSk7XG4gICAgY29uc3QgW2ltYWdlQXNwZWN0UmF0aW8sIHNldEltYWdlQXNwZWN0UmF0aW9dID0gdXNlU3RhdGUoJzE2OjknKTtcbiAgICBjb25zdCBbaW1hZ2VTaXplLCBzZXRJbWFnZVNpemVdID0gdXNlU3RhdGUoJzRLJyk7XG4gICAgY29uc3QgW2ltYWdlUHJvdmlkZXIsIHNldEltYWdlUHJvdmlkZXJdID0gdXNlU3RhdGUoJ2dvb2dsZScpO1xuICAgIGNvbnN0IFtpbWFnZVByZXNldEtleSwgc2V0SW1hZ2VQcmVzZXRLZXldID0gdXNlU3RhdGUoJ2xheWVyZWRfZWRpdG9yaWFsX2N1dG91dCcpO1xuICAgIGNvbnN0IFtpbWFnZUFkZGl0aW9uYWxLZXl3b3Jkcywgc2V0SW1hZ2VBZGRpdGlvbmFsS2V5d29yZHNdID0gdXNlU3RhdGUoJycpO1xuXG4gICAgY29uc3QgeyBpbnNlcnRCbG9ja3MgfSA9IHVzZURpc3BhdGNoKCdjb3JlL2Jsb2NrLWVkaXRvcicpO1xuICAgIGNvbnN0IHsgZWRpdFBvc3QgfSA9IHVzZURpc3BhdGNoKCdjb3JlL2VkaXRvcicpO1xuICAgIGNvbnN0IHsgY3JlYXRlTm90aWNlIH0gPSB1c2VEaXNwYXRjaCgnY29yZS9ub3RpY2VzJyk7XG5cbiAgICBjb25zdCBwb3N0SWQgPSB1c2VTZWxlY3QoKHNlbGVjdCkgPT4gc2VsZWN0KCdjb3JlL2VkaXRvcicpLmdldEN1cnJlbnRQb3N0SWQoKSwgW10pO1xuICAgIGNvbnN0IGRyYWZ0Q29udGVudCA9IHVzZVNlbGVjdCgoc2VsZWN0KSA9PiBzZWxlY3QoJ2NvcmUvZWRpdG9yJykuZ2V0RWRpdGVkUG9zdENvbnRlbnQoKSwgW10pO1xuICAgIGNvbnN0IHBvc3RUaXRsZSA9IHVzZVNlbGVjdCgoc2VsZWN0KSA9PiBzZWxlY3QoJ2NvcmUvZWRpdG9yJykuZ2V0RWRpdGVkUG9zdEF0dHJpYnV0ZSgndGl0bGUnKSB8fCAnJywgW10pO1xuICAgIGNvbnN0IHBvc3RFeGNlcnB0ID0gdXNlU2VsZWN0KChzZWxlY3QpID0+IHNlbGVjdCgnY29yZS9lZGl0b3InKS5nZXRFZGl0ZWRQb3N0QXR0cmlidXRlKCdleGNlcnB0JykgfHwgJycsIFtdKTtcblxuICAgIHVzZUVmZmVjdCgoKSA9PiB7XG4gICAgICAgIGNvbnN0IGxvYWRJbWFnZUNvbmZpZyA9IGFzeW5jICgpID0+IHtcbiAgICAgICAgICAgIHNldEltYWdlQ29uZmlnTG9hZGluZyh0cnVlKTtcbiAgICAgICAgICAgIHNldEltYWdlQ29uZmlnRXJyb3IoJycpO1xuXG4gICAgICAgICAgICB0cnkge1xuICAgICAgICAgICAgICAgIGNvbnN0IHJlc3BvbnNlID0gYXdhaXQgYXBpRmV0Y2goe1xuICAgICAgICAgICAgICAgICAgICBwYXRoOiAnZHVhbC1ncHQvdjEvaW1hZ2VzL2NvbmZpZycsXG4gICAgICAgICAgICAgICAgICAgIG1ldGhvZDogJ0dFVCcsXG4gICAgICAgICAgICAgICAgfSk7XG5cbiAgICAgICAgICAgICAgICBzZXRJbWFnZUNvbmZpZyhyZXNwb25zZSk7XG4gICAgICAgICAgICAgICAgc2V0SW1hZ2VQcm92aWRlcihyZXNwb25zZS5pbWFnZV9wcm92aWRlciB8fCAnZ29vZ2xlJyk7XG4gICAgICAgICAgICAgICAgc2V0SW1hZ2VQcmVzZXRLZXkocmVzcG9uc2UuZGVmYXVsdF9wcmVzZXRfa2V5IHx8ICdsYXllcmVkX2VkaXRvcmlhbF9jdXRvdXQnKTtcbiAgICAgICAgICAgICAgICBzZXRJbWFnZUFzcGVjdFJhdGlvKHJlc3BvbnNlLmhvdXNlX3N0eWxlPy5hc3BlY3RfcmF0aW8gfHwgJzE2OjknKTtcbiAgICAgICAgICAgICAgICBzZXRJbWFnZVN0b3JlTWVkaWEoISFyZXNwb25zZS53b3JrZmxvdz8uYXV0b19zdG9yZV9tZWRpYSk7XG4gICAgICAgICAgICAgICAgc2V0SW1hZ2VTZXRGZWF0dXJlZCghIXJlc3BvbnNlLndvcmtmbG93Py5hbGxvd19mZWF0dXJlZF9pbWFnZV9yZXBsYWNlKTtcbiAgICAgICAgICAgIH0gY2F0Y2ggKGVycm9yKSB7XG4gICAgICAgICAgICAgICAgc2V0SW1hZ2VDb25maWdFcnJvcihlcnJvcj8ubWVzc2FnZSB8fCAnRmFpbGVkIHRvIGxvYWQgaW1hZ2Ugc2V0dGluZ3MuJyk7XG4gICAgICAgICAgICB9IGZpbmFsbHkge1xuICAgICAgICAgICAgICAgIHNldEltYWdlQ29uZmlnTG9hZGluZyhmYWxzZSk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH07XG5cbiAgICAgICAgbG9hZEltYWdlQ29uZmlnKCk7XG4gICAgfSwgW10pO1xuXG4gICAgY29uc3QgaGFuZGxlUmVzZWFyY2hTdWJtaXQgPSBhc3luYyAoKSA9PiB7XG4gICAgICAgIGlmICghcmVzZWFyY2hQcm9tcHQudHJpbSgpKSB7XG4gICAgICAgICAgICBzZXRSZXNlYXJjaEVycm9yKCdQbGVhc2UgZW50ZXIgYSByZXNlYXJjaCBwcm9tcHQnKTtcbiAgICAgICAgICAgIHJldHVybjtcbiAgICAgICAgfVxuXG4gICAgICAgIHNldFJlc2VhcmNoTG9hZGluZyh0cnVlKTtcbiAgICAgICAgc2V0UmVzZWFyY2hFcnJvcignJyk7XG4gICAgICAgIHNldFJlc2VhcmNoUmVzdWx0cygnJyk7XG5cbiAgICAgICAgdHJ5IHtcbiAgICAgICAgICAgIGNvbnN0IHNlc3Npb25SZXNwb25zZSA9IGF3YWl0IGFwaUZldGNoKHtcbiAgICAgICAgICAgICAgICBwYXRoOiAnZHVhbC1ncHQvdjEvc2Vzc2lvbnMnLFxuICAgICAgICAgICAgICAgIG1ldGhvZDogJ1BPU1QnLFxuICAgICAgICAgICAgICAgIGRhdGE6IHtcbiAgICAgICAgICAgICAgICAgICAgcm9sZTogJ3Jlc2VhcmNoJyxcbiAgICAgICAgICAgICAgICAgICAgdGl0bGU6ICdSZXNlYXJjaCBTZXNzaW9uIC0gJyArIG5ldyBEYXRlKCkudG9Mb2NhbGVTdHJpbmcoKSxcbiAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgfSk7XG5cbiAgICAgICAgICAgIGNvbnN0IGpvYlJlc3BvbnNlID0gYXdhaXQgYXBpRmV0Y2goe1xuICAgICAgICAgICAgICAgIHBhdGg6ICdkdWFsLWdwdC92MS9qb2JzJyxcbiAgICAgICAgICAgICAgICBtZXRob2Q6ICdQT1NUJyxcbiAgICAgICAgICAgICAgICBkYXRhOiB7XG4gICAgICAgICAgICAgICAgICAgIHNlc3Npb25faWQ6IHNlc3Npb25SZXNwb25zZS5zZXNzaW9uX2lkLFxuICAgICAgICAgICAgICAgICAgICBwcm9tcHQ6IHJlc2VhcmNoUHJvbXB0LFxuICAgICAgICAgICAgICAgICAgICBtb2RlbDogJ2dwdC00JyxcbiAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgfSk7XG5cbiAgICAgICAgICAgIHNldFJlc2VhcmNoSm9iSWQoam9iUmVzcG9uc2Uuam9iX2lkKTtcbiAgICAgICAgICAgIHNldFJlc2VhcmNoUmVzdWx0cygnSm9iIHN1Ym1pdHRlZCBzdWNjZXNzZnVsbHkuIFByb2Nlc3NpbmcuLi4nKTtcbiAgICAgICAgICAgIHBvbGxKb2JTdGF0dXMoam9iUmVzcG9uc2Uuam9iX2lkLCAncmVzZWFyY2gnKTtcbiAgICAgICAgfSBjYXRjaCAoZXJyb3IpIHtcbiAgICAgICAgICAgIGxldCBlcnJvck1lc3NhZ2UgPSAnQW4gZXJyb3Igb2NjdXJyZWQgd2hpbGUgcHJvY2Vzc2luZyB5b3VyIHJlc2VhcmNoIHJlcXVlc3QuJztcblxuICAgICAgICAgICAgaWYgKGVycm9yLmNvZGUgPT09ICdidWRnZXRfZXhjZWVkZWQnKSB7XG4gICAgICAgICAgICAgICAgZXJyb3JNZXNzYWdlID0gJ1Rva2VuIGJ1ZGdldCBleGNlZWRlZC4gUGxlYXNlIGNvbnRhY3QgYW4gYWRtaW5pc3RyYXRvci4nO1xuICAgICAgICAgICAgfSBlbHNlIGlmIChlcnJvci5jb2RlID09PSAnaW52YWxpZF9hcGlfa2V5Jykge1xuICAgICAgICAgICAgICAgIGVycm9yTWVzc2FnZSA9ICdBUEkgY29uZmlndXJhdGlvbiBlcnJvci4gUGxlYXNlIGNvbnRhY3QgYW4gYWRtaW5pc3RyYXRvci4nO1xuICAgICAgICAgICAgfSBlbHNlIGlmIChlcnJvci5tZXNzYWdlKSB7XG4gICAgICAgICAgICAgICAgZXJyb3JNZXNzYWdlID0gZXJyb3IubWVzc2FnZTtcbiAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgc2V0UmVzZWFyY2hFcnJvcihlcnJvck1lc3NhZ2UpO1xuICAgICAgICAgICAgY3JlYXRlTm90aWNlKCdlcnJvcicsIGVycm9yTWVzc2FnZSwgeyB0eXBlOiAnc25hY2tiYXInIH0pO1xuICAgICAgICB9IGZpbmFsbHkge1xuICAgICAgICAgICAgc2V0UmVzZWFyY2hMb2FkaW5nKGZhbHNlKTtcbiAgICAgICAgfVxuICAgIH07XG5cbiAgICBjb25zdCBoYW5kbGVBdXRob3JTdWJtaXQgPSBhc3luYyAoKSA9PiB7XG4gICAgICAgIHNldEF1dGhvckxvYWRpbmcodHJ1ZSk7XG4gICAgICAgIHNldEF1dGhvckVycm9yKCcnKTtcbiAgICAgICAgc2V0QXV0aG9yUmVzdWx0cygnJyk7XG4gICAgICAgIHNldEF1dGhvckJsb2NrcyhbXSk7XG4gICAgICAgIHNldEF1dGhvckFic3RyYWN0KG51bGwpO1xuICAgICAgICBzZXRBdXRob3JXYXJuaW5ncyhbXSk7XG4gICAgICAgIHNldEF1dGhvclZhbGlkYXRpb25FcnJvcnMoW10pO1xuXG4gICAgICAgIHRyeSB7XG4gICAgICAgICAgICBjb25zdCBwYXlsb2FkID0ge1xuICAgICAgICAgICAgICAgIG1vZGU6IGF1dGhvck1vZGUsXG4gICAgICAgICAgICAgICAgZnJhbWV3b3JrX2JyaWVmX2lkOiBmcmFtZXdvcmtCcmllZklkIHx8IHVuZGVmaW5lZCxcbiAgICAgICAgICAgICAgICBwbGFubmVyX3Nlc3Npb25faWQ6IHBsYW5uZXJTZXNzaW9uSWQgfHwgdW5kZWZpbmVkLFxuICAgICAgICAgICAgICAgIGRyYWZ0X2NvbnRlbnQ6IGF1dGhvck1vZGUgIT09ICdkcmFmdCcgPyBkcmFmdENvbnRlbnQgOiB1bmRlZmluZWQsXG4gICAgICAgICAgICAgICAgaW5zdHJ1Y3Rpb25zOiBhdXRob3JJbnN0cnVjdGlvbnMgfHwgdW5kZWZpbmVkLFxuICAgICAgICAgICAgICAgIGNvcmVfc2V0dGluZ3M6IGF1dGhvckNvcmVTZXR0aW5ncyxcbiAgICAgICAgICAgIH07XG5cbiAgICAgICAgICAgIGNvbnN0IHJlc3BvbnNlID0gYXdhaXQgYXBpRmV0Y2goe1xuICAgICAgICAgICAgICAgIHBhdGg6ICdkdWFsLWdwdC92MS9hdXRob3IvcnVuJyxcbiAgICAgICAgICAgICAgICBtZXRob2Q6ICdQT1NUJyxcbiAgICAgICAgICAgICAgICBkYXRhOiBwYXlsb2FkLFxuICAgICAgICAgICAgfSk7XG5cbiAgICAgICAgICAgIHNldEF1dGhvcldhcm5pbmdzKHJlc3BvbnNlLndhcm5pbmdzIHx8IFtdKTtcbiAgICAgICAgICAgIHNldEF1dGhvclZhbGlkYXRpb25FcnJvcnMocmVzcG9uc2UudmFsaWRhdGlvbl9lcnJvcnMgfHwgW10pO1xuXG4gICAgICAgICAgICBpZiAocmVzcG9uc2UubW9kZSA9PT0gJ2RyYWZ0Jykge1xuICAgICAgICAgICAgICAgIHNldEF1dGhvckJsb2NrcyhyZXNwb25zZS5vdXRwdXQ/LmJsb2NrcyB8fCBbXSk7XG4gICAgICAgICAgICAgICAgc2V0QXV0aG9yUmVzdWx0cygnRHJhZnQgY29tcGxldGVkIHN1Y2Nlc3NmdWxseS4nKTtcbiAgICAgICAgICAgIH0gZWxzZSBpZiAocmVzcG9uc2UubW9kZSA9PT0gJ2Fic3RyYWN0Jykge1xuICAgICAgICAgICAgICAgIHNldEF1dGhvckFic3RyYWN0KHJlc3BvbnNlLm91dHB1dD8uYWJzdHJhY3QgfHwgbnVsbCk7XG4gICAgICAgICAgICAgICAgc2V0QXV0aG9yUmVzdWx0cygnQWJzdHJhY3QgY29tcGxldGVkIHN1Y2Nlc3NmdWxseS4nKTtcbiAgICAgICAgICAgIH0gZWxzZSBpZiAocmVzcG9uc2UubW9kZSA9PT0gJ2VucmljaG1lbnQnKSB7XG4gICAgICAgICAgICAgICAgc2V0QXV0aG9yQmxvY2tzKHJlc3BvbnNlLm91dHB1dD8uYmxvY2tzIHx8IFtdKTtcbiAgICAgICAgICAgICAgICBzZXRBdXRob3JSZXN1bHRzKCdFbnJpY2htZW50IGNvbXBsZXRlZCBzdWNjZXNzZnVsbHkuJyk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH0gY2F0Y2ggKGVycm9yKSB7XG4gICAgICAgICAgICBsZXQgZXJyb3JNZXNzYWdlID0gJ0FuIGVycm9yIG9jY3VycmVkIHdoaWxlIHByb2Nlc3NpbmcgeW91ciBhdXRob3JpbmcgcmVxdWVzdC4nO1xuXG4gICAgICAgICAgICBpZiAoZXJyb3IuY29kZSA9PT0gJ2J1ZGdldF9leGNlZWRlZCcpIHtcbiAgICAgICAgICAgICAgICBlcnJvck1lc3NhZ2UgPSAnVG9rZW4gYnVkZ2V0IGV4Y2VlZGVkLiBQbGVhc2UgY29udGFjdCBhbiBhZG1pbmlzdHJhdG9yLic7XG4gICAgICAgICAgICB9IGVsc2UgaWYgKGVycm9yLmNvZGUgPT09ICdpbnZhbGlkX2FwaV9rZXknKSB7XG4gICAgICAgICAgICAgICAgZXJyb3JNZXNzYWdlID0gJ0FQSSBjb25maWd1cmF0aW9uIGVycm9yLiBQbGVhc2UgY29udGFjdCBhbiBhZG1pbmlzdHJhdG9yLic7XG4gICAgICAgICAgICB9IGVsc2UgaWYgKGVycm9yLm1lc3NhZ2UpIHtcbiAgICAgICAgICAgICAgICBlcnJvck1lc3NhZ2UgPSBlcnJvci5tZXNzYWdlO1xuICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICBzZXRBdXRob3JFcnJvcihlcnJvck1lc3NhZ2UpO1xuICAgICAgICAgICAgY3JlYXRlTm90aWNlKCdlcnJvcicsIGVycm9yTWVzc2FnZSwgeyB0eXBlOiAnc25hY2tiYXInIH0pO1xuICAgICAgICB9IGZpbmFsbHkge1xuICAgICAgICAgICAgc2V0QXV0aG9yTG9hZGluZyhmYWxzZSk7XG4gICAgICAgIH1cbiAgICB9O1xuXG4gICAgY29uc3QgcG9sbEpvYlN0YXR1cyA9IGFzeW5jIChqb2JJZCwgdHlwZSkgPT4ge1xuICAgICAgICB0cnkge1xuICAgICAgICAgICAgY29uc3QgcmVzcG9uc2UgPSBhd2FpdCBhcGlGZXRjaCh7XG4gICAgICAgICAgICAgICAgcGF0aDogYGR1YWwtZ3B0L3YxL2pvYnMvJHtqb2JJZH1gLFxuICAgICAgICAgICAgICAgIG1ldGhvZDogJ0dFVCcsXG4gICAgICAgICAgICB9KTtcblxuICAgICAgICAgICAgaWYgKHJlc3BvbnNlLnN0YXR1cyA9PT0gJ2NvbXBsZXRlZCcpIHtcbiAgICAgICAgICAgICAgICBpZiAodHlwZSA9PT0gJ3Jlc2VhcmNoJykge1xuICAgICAgICAgICAgICAgICAgICBzZXRSZXNlYXJjaFJlc3VsdHMoJ1Jlc2VhcmNoIGNvbXBsZXRlZCBzdWNjZXNzZnVsbHkuJyk7XG4gICAgICAgICAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgICAgICAgICAgc2V0QXV0aG9yUmVzdWx0cygnQ29udGVudCBnZW5lcmF0aW9uIGNvbXBsZXRlZCBzdWNjZXNzZnVsbHkuJyk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgfSBlbHNlIGlmIChyZXNwb25zZS5zdGF0dXMgPT09ICdmYWlsZWQnKSB7XG4gICAgICAgICAgICAgICAgY29uc3QgZXJyb3JNc2cgPSByZXNwb25zZS5lcnJvcl9tZXNzYWdlIHx8ICdKb2IgZmFpbGVkJztcbiAgICAgICAgICAgICAgICBpZiAodHlwZSA9PT0gJ3Jlc2VhcmNoJykge1xuICAgICAgICAgICAgICAgICAgICBzZXRSZXNlYXJjaEVycm9yKGVycm9yTXNnKTtcbiAgICAgICAgICAgICAgICB9IGVsc2Uge1xuICAgICAgICAgICAgICAgICAgICBzZXRBdXRob3JFcnJvcihlcnJvck1zZyk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIGNyZWF0ZU5vdGljZSgnZXJyb3InLCBgSm9iIGZhaWxlZDogJHtlcnJvck1zZ31gLCB7IHR5cGU6ICdzbmFja2JhcicgfSk7XG4gICAgICAgICAgICB9IGVsc2Uge1xuICAgICAgICAgICAgICAgIHNldFRpbWVvdXQoKCkgPT4gcG9sbEpvYlN0YXR1cyhqb2JJZCwgdHlwZSksIDIwMDApO1xuICAgICAgICAgICAgfVxuICAgICAgICB9IGNhdGNoIChlcnJvcikge1xuICAgICAgICAgICAgY29uc3QgZXJyb3JNc2cgPSAnRXJyb3IgY2hlY2tpbmcgam9iIHN0YXR1cyc7XG4gICAgICAgICAgICBpZiAodHlwZSA9PT0gJ3Jlc2VhcmNoJykge1xuICAgICAgICAgICAgICAgIHNldFJlc2VhcmNoRXJyb3IoZXJyb3JNc2cpO1xuICAgICAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgICAgICBzZXRBdXRob3JFcnJvcihlcnJvck1zZyk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH1cbiAgICB9O1xuXG4gICAgY29uc3QgaW5zZXJ0QmxvY2tzRnJvbUF1dGhvciA9ICgpID0+IHtcbiAgICAgICAgaWYgKCFhdXRob3JCbG9ja3MgfHwgYXV0aG9yQmxvY2tzLmxlbmd0aCA9PT0gMCkge1xuICAgICAgICAgICAgcmV0dXJuO1xuICAgICAgICB9XG5cbiAgICAgICAgY29uc3QgZXNjYXBlSHRtbCA9ICh2YWx1ZSkgPT4ge1xuICAgICAgICAgICAgaWYgKHZhbHVlID09PSBudWxsIHx8IHZhbHVlID09PSB1bmRlZmluZWQpIHtcbiAgICAgICAgICAgICAgICByZXR1cm4gJyc7XG4gICAgICAgICAgICB9XG4gICAgICAgICAgICByZXR1cm4gU3RyaW5nKHZhbHVlKVxuICAgICAgICAgICAgICAgIC5yZXBsYWNlKC8mL2csICcmYW1wOycpXG4gICAgICAgICAgICAgICAgLnJlcGxhY2UoLzwvZywgJyZsdDsnKVxuICAgICAgICAgICAgICAgIC5yZXBsYWNlKC8+L2csICcmZ3Q7JylcbiAgICAgICAgICAgICAgICAucmVwbGFjZSgvXCIvZywgJyZxdW90OycpXG4gICAgICAgICAgICAgICAgLnJlcGxhY2UoLycvZywgJyYjMDM5OycpO1xuICAgICAgICB9O1xuXG4gICAgICAgIGNvbnN0IGJ1aWxkUHVsbHF1b3RlTWV0YVNwYW4gPSAobWV0YSkgPT4ge1xuICAgICAgICAgICAgaWYgKCFtZXRhIHx8IHR5cGVvZiBtZXRhICE9PSAnb2JqZWN0Jykge1xuICAgICAgICAgICAgICAgIHJldHVybiAnJztcbiAgICAgICAgICAgIH1cbiAgICAgICAgICAgIGNvbnN0IGF0dHJpYnV0ZXMgPSBbXG4gICAgICAgICAgICAgICAgYGRhdGEtc291cmNlLWF1dGhvcj1cIiR7ZXNjYXBlSHRtbChtZXRhLnNvdXJjZV9hdXRob3IgfHwgJycpfVwiYCxcbiAgICAgICAgICAgICAgICBgZGF0YS1wdWJsaWNhdGlvbj1cIiR7ZXNjYXBlSHRtbChtZXRhLnB1YmxpY2F0aW9uIHx8ICcnKX1cImAsXG4gICAgICAgICAgICAgICAgYGRhdGEtb3JnYW5pc2F0aW9uPVwiJHtlc2NhcGVIdG1sKG1ldGEub3JnYW5pc2F0aW9uIHx8ICcnKX1cImAsXG4gICAgICAgICAgICAgICAgYGRhdGEtZGF0ZT1cIiR7ZXNjYXBlSHRtbChtZXRhLmRhdGUgfHwgJycpfVwiYCxcbiAgICAgICAgICAgICAgICBgZGF0YS1jaXRhdGlvbi1yZWYtaWQ9XCIke2VzY2FwZUh0bWwobWV0YS5jaXRhdGlvbl9yZWZfaWQgfHwgJycpfVwiYCxcbiAgICAgICAgICAgIF07XG5cbiAgICAgICAgICAgIHJldHVybiBgPHNwYW4gY2xhc3M9XCJkdWFsLWdwdC1wdWxscXVvdGUtbWV0YVwiIHN0eWxlPVwiZGlzcGxheTpub25lXCIgJHthdHRyaWJ1dGVzLmpvaW4oJyAnKX0+PC9zcGFuPmA7XG4gICAgICAgIH07XG5cbiAgICAgICAgY29uc3QgYmxvY2tzID0gYXV0aG9yQmxvY2tzLm1hcCgoYmxvY2spID0+IHtcbiAgICAgICAgICAgIHN3aXRjaCAoYmxvY2sudHlwZSkge1xuICAgICAgICAgICAgICAgIGNhc2UgJ2hlYWRpbmcnOlxuICAgICAgICAgICAgICAgICAgICByZXR1cm4gd3AuYmxvY2tzLmNyZWF0ZUJsb2NrKCdjb3JlL2hlYWRpbmcnLCB7XG4gICAgICAgICAgICAgICAgICAgICAgICBsZXZlbDogYmxvY2subGV2ZWwgfHwgMixcbiAgICAgICAgICAgICAgICAgICAgICAgIGNvbnRlbnQ6IGJsb2NrLmNvbnRlbnQgfHwgJycsXG4gICAgICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgIGNhc2UgJ3BhcmFncmFwaCc6XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiB3cC5ibG9ja3MuY3JlYXRlQmxvY2soJ2NvcmUvcGFyYWdyYXBoJywge1xuICAgICAgICAgICAgICAgICAgICAgICAgY29udGVudDogYmxvY2suY29udGVudCB8fCAnJyxcbiAgICAgICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICAgICAgY2FzZSAnbGlzdCc6XG4gICAgICAgICAgICAgICAgICAgIGNvbnN0IGxpc3RJdGVtcyA9IChibG9jay5pdGVtcyB8fCBbXSkubWFwKChpdGVtKSA9PiBgPGxpPiR7ZXNjYXBlSHRtbChpdGVtKX08L2xpPmApLmpvaW4oJycpO1xuICAgICAgICAgICAgICAgICAgICBjb25zdCBsaXN0VGFnID0gYmxvY2sub3JkZXJlZCA/ICdvbCcgOiAndWwnO1xuICAgICAgICAgICAgICAgICAgICByZXR1cm4gd3AuYmxvY2tzLmNyZWF0ZUJsb2NrKCdjb3JlL2xpc3QnLCB7XG4gICAgICAgICAgICAgICAgICAgICAgICBvcmRlcmVkOiAhIWJsb2NrLm9yZGVyZWQsXG4gICAgICAgICAgICAgICAgICAgICAgICB2YWx1ZXM6IGA8JHtsaXN0VGFnfT4ke2xpc3RJdGVtc308LyR7bGlzdFRhZ30+YCxcbiAgICAgICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICAgICAgY2FzZSAncHVsbHF1b3RlJzpcbiAgICAgICAgICAgICAgICAgICAgY29uc3QgcHVsbHF1b3RlTWV0YSA9IGJ1aWxkUHVsbHF1b3RlTWV0YVNwYW4oYmxvY2subWV0YSB8fCBibG9jay5tZXRhZGF0YSk7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiB3cC5ibG9ja3MuY3JlYXRlQmxvY2soJ2NvcmUvcHVsbHF1b3RlJywge1xuICAgICAgICAgICAgICAgICAgICAgICAgdmFsdWU6IGA8cD4ke2VzY2FwZUh0bWwoYmxvY2suY29udGVudCB8fCAnJyl9PC9wPiR7cHVsbHF1b3RlTWV0YX1gLFxuICAgICAgICAgICAgICAgICAgICAgICAgY2l0YXRpb246IGVzY2FwZUh0bWwoYmxvY2suY2l0ZSB8fCAnJyksXG4gICAgICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgIGNhc2UgJ3F1b3RlJzpcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHdwLmJsb2Nrcy5jcmVhdGVCbG9jaygnY29yZS9xdW90ZScsIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIHZhbHVlOiBgPHA+JHtlc2NhcGVIdG1sKGJsb2NrLmNvbnRlbnQgfHwgJycpfTwvcD5gLFxuICAgICAgICAgICAgICAgICAgICAgICAgY2l0YXRpb246IGVzY2FwZUh0bWwoYmxvY2suY2l0ZSB8fCAnJyksXG4gICAgICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgIGNhc2UgJ3NlcGFyYXRvcic6XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiB3cC5ibG9ja3MuY3JlYXRlQmxvY2soJ2NvcmUvc2VwYXJhdG9yJywge30pO1xuICAgICAgICAgICAgICAgIGRlZmF1bHQ6XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiB3cC5ibG9ja3MuY3JlYXRlQmxvY2soJ2NvcmUvcGFyYWdyYXBoJywge1xuICAgICAgICAgICAgICAgICAgICAgICAgY29udGVudDogYmxvY2suY29udGVudCB8fCAnJyxcbiAgICAgICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH0pO1xuXG4gICAgICAgIGluc2VydEJsb2NrcyhibG9ja3MpO1xuICAgIH07XG5cbiAgICBjb25zdCBidWlsZEltYWdlUGF5bG9hZCA9ICgpID0+ICh7XG4gICAgICAgIHBvc3RfaWQ6IHBvc3RJZCxcbiAgICAgICAgdGl0bGU6IHBvc3RUaXRsZSxcbiAgICAgICAgc3VtbWFyeTogcG9zdEV4Y2VycHQgfHwgJycsXG4gICAgICAgIHByb21wdDogaW1hZ2VQcm9tcHQsXG4gICAgICAgIG5lZ2F0aXZlX3Byb21wdDogaW1hZ2VOZWdhdGl2ZVByb21wdCxcbiAgICAgICAgYWx0X3RleHQ6IGltYWdlQWx0VGV4dCxcbiAgICAgICAgY2FwdGlvbjogaW1hZ2VDYXB0aW9uLFxuICAgICAgICBwcm92aWRlcjogaW1hZ2VQcm92aWRlcixcbiAgICAgICAgcHJlc2V0X2tleTogaW1hZ2VQcmVzZXRLZXksXG4gICAgICAgIGtleXdvcmRzOiBpbWFnZUFkZGl0aW9uYWxLZXl3b3JkcyxcbiAgICAgICAgdGV4dF9pbl9pbWFnZTogaW1hZ2VUZXh0SW5JbWFnZSxcbiAgICAgICAgZWRpdG9yaWFsX2FjY3VyYWN5OiBpbWFnZUVkaXRvcmlhbEFjY3VyYWN5LFxuICAgICAgICBzdG9yZV9pbl9tZWRpYV9saWJyYXJ5OiBpbWFnZVN0b3JlTWVkaWEsXG4gICAgICAgIHNldF9mZWF0dXJlZF9pbWFnZTogaW1hZ2VTZXRGZWF0dXJlZCxcbiAgICAgICAgYXNwZWN0X3JhdGlvOiBpbWFnZUFzcGVjdFJhdGlvLFxuICAgICAgICBpbWFnZV9zaXplOiBpbWFnZVNpemUsXG4gICAgfSk7XG5cbiAgICBjb25zdCBoYW5kbGVSZWNvbW1lbmRJbWFnZSA9IGFzeW5jICgpID0+IHtcbiAgICAgICAgc2V0SW1hZ2VSZWNvbW1lbmRhdGlvbkxvYWRpbmcodHJ1ZSk7XG4gICAgICAgIHNldEltYWdlRXJyb3IoJycpO1xuICAgICAgICBzZXRJbWFnZU5vdGljZSgnJyk7XG5cbiAgICAgICAgdHJ5IHtcbiAgICAgICAgICAgIGNvbnN0IHJlc3BvbnNlID0gYXdhaXQgYXBpRmV0Y2goe1xuICAgICAgICAgICAgICAgIHBhdGg6ICdkdWFsLWdwdC92MS9pbWFnZXMvcmVjb21tZW5kJyxcbiAgICAgICAgICAgICAgICBtZXRob2Q6ICdQT1NUJyxcbiAgICAgICAgICAgICAgICBkYXRhOiBidWlsZEltYWdlUGF5bG9hZCgpLFxuICAgICAgICAgICAgfSk7XG5cbiAgICAgICAgICAgIHNldEltYWdlUmVjb21tZW5kYXRpb24ocmVzcG9uc2UpO1xuICAgICAgICAgICAgc2V0SW1hZ2VQcm9tcHQocmVzcG9uc2UucHJvbXB0IHx8ICcnKTtcbiAgICAgICAgICAgIHNldEltYWdlTmVnYXRpdmVQcm9tcHQocmVzcG9uc2UubmVnYXRpdmVfcHJvbXB0IHx8ICcnKTtcbiAgICAgICAgICAgIHNldEltYWdlQWx0VGV4dChyZXNwb25zZS5hbHRfdGV4dCB8fCAnJyk7XG4gICAgICAgICAgICBzZXRJbWFnZUNhcHRpb24ocmVzcG9uc2UuY2FwdGlvbiB8fCAnJyk7XG4gICAgICAgICAgICBzZXRJbWFnZU5vdGljZSgnSW1hZ2UgcmVjb21tZW5kYXRpb24gdXBkYXRlZCBmcm9tIHRoZSBjdXJyZW50IGFydGljbGUgY29udGV4dC4nKTtcbiAgICAgICAgfSBjYXRjaCAoZXJyb3IpIHtcbiAgICAgICAgICAgIHNldEltYWdlRXJyb3IoZXJyb3I/Lm1lc3NhZ2UgfHwgJ0ZhaWxlZCB0byBnZW5lcmF0ZSBhbiBpbWFnZSByZWNvbW1lbmRhdGlvbi4nKTtcbiAgICAgICAgfSBmaW5hbGx5IHtcbiAgICAgICAgICAgIHNldEltYWdlUmVjb21tZW5kYXRpb25Mb2FkaW5nKGZhbHNlKTtcbiAgICAgICAgfVxuICAgIH07XG5cbiAgICBjb25zdCBoYW5kbGVHZW5lcmF0ZUltYWdlID0gYXN5bmMgKCkgPT4ge1xuICAgICAgICBzZXRJbWFnZUdlbmVyYXRlTG9hZGluZyh0cnVlKTtcbiAgICAgICAgc2V0SW1hZ2VFcnJvcignJyk7XG4gICAgICAgIHNldEltYWdlTm90aWNlKCcnKTtcblxuICAgICAgICB0cnkge1xuICAgICAgICAgICAgY29uc3QgcmVzcG9uc2UgPSBhd2FpdCBhcGlGZXRjaCh7XG4gICAgICAgICAgICAgICAgcGF0aDogJ2R1YWwtZ3B0L3YxL2ltYWdlcy9nZW5lcmF0ZScsXG4gICAgICAgICAgICAgICAgbWV0aG9kOiAnUE9TVCcsXG4gICAgICAgICAgICAgICAgZGF0YTogYnVpbGRJbWFnZVBheWxvYWQoKSxcbiAgICAgICAgICAgIH0pO1xuXG4gICAgICAgICAgICBzZXRJbWFnZVJlc3VsdChyZXNwb25zZSk7XG4gICAgICAgICAgICBzZXRJbWFnZU5vdGljZShyZXNwb25zZS5zdG9yZWRfaW5fbWVkaWFfbGlicmFyeVxuICAgICAgICAgICAgICAgID8gJ0ltYWdlIGdlbmVyYXRlZCBhbmQgc2F2ZWQgdG8gdGhlIG1lZGlhIGxpYnJhcnkuJ1xuICAgICAgICAgICAgICAgIDogJ0ltYWdlIGdlbmVyYXRlZCBzdWNjZXNzZnVsbHkuJyk7XG5cbiAgICAgICAgICAgIGNvbnN0IGZpcnN0QXR0YWNobWVudCA9IHJlc3BvbnNlLmF0dGFjaG1lbnRzPy5bMF07XG4gICAgICAgICAgICBpZiAoZmlyc3RBdHRhY2htZW50Py5hdHRhY2htZW50X2lkICYmIGltYWdlU2V0RmVhdHVyZWQpIHtcbiAgICAgICAgICAgICAgICBlZGl0UG9zdCh7IGZlYXR1cmVkX21lZGlhOiBmaXJzdEF0dGFjaG1lbnQuYXR0YWNobWVudF9pZCB9KTtcbiAgICAgICAgICAgIH1cbiAgICAgICAgfSBjYXRjaCAoZXJyb3IpIHtcbiAgICAgICAgICAgIHNldEltYWdlRXJyb3IoZXJyb3I/Lm1lc3NhZ2UgfHwgJ0ZhaWxlZCB0byBnZW5lcmF0ZSBpbWFnZS4nKTtcbiAgICAgICAgfSBmaW5hbGx5IHtcbiAgICAgICAgICAgIHNldEltYWdlR2VuZXJhdGVMb2FkaW5nKGZhbHNlKTtcbiAgICAgICAgfVxuICAgIH07XG5cbiAgICBjb25zdCBpbnNlcnRHZW5lcmF0ZWRJbWFnZSA9ICgpID0+IHtcbiAgICAgICAgY29uc3QgZmlyc3RBdHRhY2htZW50ID0gaW1hZ2VSZXN1bHQ/LmF0dGFjaG1lbnRzPy5bMF07XG4gICAgICAgIGlmICghZmlyc3RBdHRhY2htZW50Py5hdHRhY2htZW50X2lkIHx8ICFmaXJzdEF0dGFjaG1lbnQ/LnVybCkge1xuICAgICAgICAgICAgcmV0dXJuO1xuICAgICAgICB9XG5cbiAgICAgICAgY29uc3QgaW1hZ2VCbG9jayA9IHdwLmJsb2Nrcy5jcmVhdGVCbG9jaygnY29yZS9pbWFnZScsIHtcbiAgICAgICAgICAgIGlkOiBmaXJzdEF0dGFjaG1lbnQuYXR0YWNobWVudF9pZCxcbiAgICAgICAgICAgIHVybDogZmlyc3RBdHRhY2htZW50LnVybCxcbiAgICAgICAgICAgIGFsdDogaW1hZ2VBbHRUZXh0LFxuICAgICAgICAgICAgY2FwdGlvbjogaW1hZ2VDYXB0aW9uLFxuICAgICAgICB9KTtcblxuICAgICAgICBpbnNlcnRCbG9ja3MoW2ltYWdlQmxvY2tdKTtcbiAgICAgICAgY3JlYXRlTm90aWNlKCdzdWNjZXNzJywgJ0dlbmVyYXRlZCBpbWFnZSBpbnNlcnRlZCBpbnRvIHRoZSBwb3N0LicsIHsgdHlwZTogJ3NuYWNrYmFyJyB9KTtcbiAgICB9O1xuXG4gICAgcmV0dXJuIChcbiAgICAgICAgPFBsdWdpblNpZGViYXJcbiAgICAgICAgICAgIG5hbWU9XCJkdWFsLWdwdC1zaWRlYmFyXCJcbiAgICAgICAgICAgIHRpdGxlPVwiRHVhbC1HUFQgQXV0aG9yaW5nXCJcbiAgICAgICAgICAgIGljb249XCJhZG1pbi10b29sc1wiXG4gICAgICAgID5cbiAgICAgICAgICAgIDxQYW5lbEJvZHkgdGl0bGU9XCJBSSBJbWFnZXNcIiBpbml0aWFsT3Blbj17dHJ1ZX0+XG4gICAgICAgICAgICAgICAge2ltYWdlQ29uZmlnTG9hZGluZyA/IChcbiAgICAgICAgICAgICAgICAgICAgPFNwaW5uZXIgLz5cbiAgICAgICAgICAgICAgICApIDogbnVsbH1cblxuICAgICAgICAgICAgICAgIHtpbWFnZUNvbmZpZ0Vycm9yID8gKFxuICAgICAgICAgICAgICAgICAgICA8U3RhdHVzTWVzc2FnZSB0b25lPVwiZXJyb3JcIiB0aXRsZT1cIkltYWdlIHNldHRpbmdzIHVuYXZhaWxhYmxlXCI+XG4gICAgICAgICAgICAgICAgICAgICAgICB7aW1hZ2VDb25maWdFcnJvcn1cbiAgICAgICAgICAgICAgICAgICAgPC9TdGF0dXNNZXNzYWdlPlxuICAgICAgICAgICAgICAgICkgOiBudWxsfVxuXG4gICAgICAgICAgICAgICAgeyFpbWFnZUNvbmZpZ0xvYWRpbmcgJiYgIWltYWdlQ29uZmlnRXJyb3IgPyAoXG4gICAgICAgICAgICAgICAgICAgIDw+XG4gICAgICAgICAgICAgICAgICAgICAgICA8U2VsZWN0Q29udHJvbFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGxhYmVsPVwiU3R5bGUgUHJlc2V0XCJcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB2YWx1ZT17aW1hZ2VQcmVzZXRLZXl9XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgb3B0aW9ucz17T2JqZWN0LmVudHJpZXMoaW1hZ2VDb25maWc/LnByZXNldHMgfHwge30pLm1hcCgoW3ZhbHVlLCBwcmVzZXRdKSA9PiAoe1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBsYWJlbDogcHJlc2V0LmxhYmVsIHx8IHZhbHVlLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB2YWx1ZSxcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB9KSl9XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgb25DaGFuZ2U9eyh2YWx1ZSkgPT4ge1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBzZXRJbWFnZVByZXNldEtleSh2YWx1ZSk7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGNvbnN0IHByZXNldCA9IGltYWdlQ29uZmlnPy5wcmVzZXRzPy5bdmFsdWVdO1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBpZiAocHJlc2V0Py5hc3BlY3RfcmF0aW8pIHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHNldEltYWdlQXNwZWN0UmF0aW8ocHJlc2V0LmFzcGVjdF9yYXRpbyk7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB9fVxuICAgICAgICAgICAgICAgICAgICAgICAgLz5cblxuICAgICAgICAgICAgICAgICAgICAgICAgPFNlbGVjdENvbnRyb2xcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBsYWJlbD1cIkltYWdlIFByb3ZpZGVyXCJcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB2YWx1ZT17aW1hZ2VQcm92aWRlcn1cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBvcHRpb25zPXtPYmplY3QuZW50cmllcyhpbWFnZUNvbmZpZz8ucHJvdmlkZXJfc3RhdHVzIHx8IHt9KVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAuZmlsdGVyKChbLCBwcm92aWRlcl0pID0+IChwcm92aWRlci5zdXBwb3J0cyB8fCBbXSkuaW5jbHVkZXMoJ2ltYWdlJykpXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIC5tYXAoKFt2YWx1ZSwgcHJvdmlkZXJdKSA9PiAoe1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgbGFiZWw6IGAke3Byb3ZpZGVyLmxhYmVsfSR7cHJvdmlkZXIuY29uZmlndXJlZCA/ICcnIDogJyAobm90IGNvbmZpZ3VyZWQpJ31gLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgdmFsdWUsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBkaXNhYmxlZDogIXByb3ZpZGVyLmVuYWJsZWQsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIH0pKX1cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBvbkNoYW5nZT17c2V0SW1hZ2VQcm92aWRlcn1cbiAgICAgICAgICAgICAgICAgICAgICAgIC8+XG5cbiAgICAgICAgICAgICAgICAgICAgICAgIDxTZWxlY3RDb250cm9sXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgbGFiZWw9XCJBc3BlY3QgUmF0aW9cIlxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIHZhbHVlPXtpbWFnZUFzcGVjdFJhdGlvfVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIG9wdGlvbnM9e1tcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgeyBsYWJlbDogJzE2OjknLCB2YWx1ZTogJzE2OjknIH0sXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHsgbGFiZWw6ICc0OjMnLCB2YWx1ZTogJzQ6MycgfSxcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgeyBsYWJlbDogJzM6NCcsIHZhbHVlOiAnMzo0JyB9LFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB7IGxhYmVsOiAnMToxJywgdmFsdWU6ICcxOjEnIH0sXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHsgbGFiZWw6ICc5OjE2JywgdmFsdWU6ICc5OjE2JyB9LFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIF19XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgb25DaGFuZ2U9e3NldEltYWdlQXNwZWN0UmF0aW99XG4gICAgICAgICAgICAgICAgICAgICAgICAvPlxuXG4gICAgICAgICAgICAgICAgICAgICAgICA8U2VsZWN0Q29udHJvbFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGxhYmVsPVwiSW1hZ2UgU2l6ZVwiXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgdmFsdWU9e2ltYWdlU2l6ZX1cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBvcHRpb25zPXtbXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHsgbGFiZWw6ICcySycsIHZhbHVlOiAnMksnIH0sXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHsgbGFiZWw6ICc0SycsIHZhbHVlOiAnNEsnIH0sXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgXX1cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBvbkNoYW5nZT17c2V0SW1hZ2VTaXplfVxuICAgICAgICAgICAgICAgICAgICAgICAgLz5cblxuICAgICAgICAgICAgICAgICAgICAgICAgPFRleHRDb250cm9sXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgbGFiZWw9XCJBZGRpdGlvbmFsIEtleXdvcmRzXCJcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB2YWx1ZT17aW1hZ2VBZGRpdGlvbmFsS2V5d29yZHN9XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgb25DaGFuZ2U9e3NldEltYWdlQWRkaXRpb25hbEtleXdvcmRzfVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIHBsYWNlaG9sZGVyPVwiT3B0aW9uYWwgdGhlbWVzLCBvYmplY3RzLCBzZWN0b3JzLCBvciBtb3RpZnNcIlxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGhlbHA9XCJDb21tYS1zZXBhcmF0ZWQga2V5d29yZHMgdG8gc3RlZXIgdGhlIGltYWdlIHdpdGhvdXQgcmV3cml0aW5nIHRoZSBmdWxsIHByb21wdC5cIlxuICAgICAgICAgICAgICAgICAgICAgICAgLz5cblxuICAgICAgICAgICAgICAgICAgICAgICAgPFRleHRDb250cm9sXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgbGFiZWw9XCJUZXh0IEluIEltYWdlXCJcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB2YWx1ZT17aW1hZ2VUZXh0SW5JbWFnZX1cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBvbkNoYW5nZT17c2V0SW1hZ2VUZXh0SW5JbWFnZX1cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBwbGFjZWhvbGRlcj1cIk9wdGlvbmFsIGV4YWN0IHRleHQgdG8gcmVuZGVyXCJcbiAgICAgICAgICAgICAgICAgICAgICAgIC8+XG5cbiAgICAgICAgICAgICAgICAgICAgICAgIDxUZXh0YXJlYUNvbnRyb2xcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBsYWJlbD1cIlByb21wdFwiXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgdmFsdWU9e2ltYWdlUHJvbXB0fVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIG9uQ2hhbmdlPXtzZXRJbWFnZVByb21wdH1cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBwbGFjZWhvbGRlcj1cIkdlbmVyYXRlIGEgcmVjb21tZW5kYXRpb24gZmlyc3QsIG9yIHdyaXRlIHlvdXIgb3duIHByb21wdC5cIlxuICAgICAgICAgICAgICAgICAgICAgICAgLz5cblxuICAgICAgICAgICAgICAgICAgICAgICAgPFRleHRhcmVhQ29udHJvbFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGxhYmVsPVwiTmVnYXRpdmUgUHJvbXB0XCJcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB2YWx1ZT17aW1hZ2VOZWdhdGl2ZVByb21wdH1cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBvbkNoYW5nZT17c2V0SW1hZ2VOZWdhdGl2ZVByb21wdH1cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBwbGFjZWhvbGRlcj1cIk9wdGlvbmFsIGV4Y2x1c2lvbnNcIlxuICAgICAgICAgICAgICAgICAgICAgICAgLz5cblxuICAgICAgICAgICAgICAgICAgICAgICAgPFRleHRDb250cm9sXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgbGFiZWw9XCJBbHQgVGV4dFwiXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgdmFsdWU9e2ltYWdlQWx0VGV4dH1cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBvbkNoYW5nZT17c2V0SW1hZ2VBbHRUZXh0fVxuICAgICAgICAgICAgICAgICAgICAgICAgLz5cblxuICAgICAgICAgICAgICAgICAgICAgICAgPFRleHRDb250cm9sXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgbGFiZWw9XCJDYXB0aW9uXCJcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB2YWx1ZT17aW1hZ2VDYXB0aW9ufVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIG9uQ2hhbmdlPXtzZXRJbWFnZUNhcHRpb259XG4gICAgICAgICAgICAgICAgICAgICAgICAvPlxuXG4gICAgICAgICAgICAgICAgICAgICAgICA8VG9nZ2xlQ29udHJvbFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGxhYmVsPVwiRWRpdG9yaWFsIEFjY3VyYWN5IC8gR29vZ2xlIFNlYXJjaCBHcm91bmRpbmdcIlxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGNoZWNrZWQ9e2ltYWdlRWRpdG9yaWFsQWNjdXJhY3l9XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgb25DaGFuZ2U9e3NldEltYWdlRWRpdG9yaWFsQWNjdXJhY3l9XG4gICAgICAgICAgICAgICAgICAgICAgICAvPlxuXG4gICAgICAgICAgICAgICAgICAgICAgICA8VG9nZ2xlQ29udHJvbFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGxhYmVsPVwiU2F2ZSBUbyBNZWRpYSBMaWJyYXJ5XCJcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBjaGVja2VkPXtpbWFnZVN0b3JlTWVkaWF9XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgb25DaGFuZ2U9e3NldEltYWdlU3RvcmVNZWRpYX1cbiAgICAgICAgICAgICAgICAgICAgICAgIC8+XG5cbiAgICAgICAgICAgICAgICAgICAgICAgIDxUb2dnbGVDb250cm9sXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgbGFiZWw9XCJTZXQgQXMgRmVhdHVyZWQgSW1hZ2VcIlxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGNoZWNrZWQ9e2ltYWdlU2V0RmVhdHVyZWR9XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgb25DaGFuZ2U9e3NldEltYWdlU2V0RmVhdHVyZWR9XG4gICAgICAgICAgICAgICAgICAgICAgICAvPlxuXG4gICAgICAgICAgICAgICAgICAgICAgICA8ZGl2IGNsYXNzTmFtZT1cImR1YWwtZ3B0LWJ1dHRvbi1yb3dcIj5cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICA8QnV0dG9uXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGlzU2Vjb25kYXJ5XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIG9uQ2xpY2s9e2hhbmRsZVJlY29tbWVuZEltYWdlfVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBkaXNhYmxlZD17aW1hZ2VSZWNvbW1lbmRhdGlvbkxvYWRpbmcgfHwgaW1hZ2VHZW5lcmF0ZUxvYWRpbmd9XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgPlxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB7aW1hZ2VSZWNvbW1lbmRhdGlvbkxvYWRpbmcgPyA8U3Bpbm5lciAvPiA6ICdSZWNvbW1lbmQnfVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIDwvQnV0dG9uPlxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxCdXR0b25cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgaXNQcmltYXJ5XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIG9uQ2xpY2s9e2hhbmRsZUdlbmVyYXRlSW1hZ2V9XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGRpc2FibGVkPXtpbWFnZUdlbmVyYXRlTG9hZGluZyB8fCBpbWFnZVJlY29tbWVuZGF0aW9uTG9hZGluZyB8fCAhaW1hZ2VQcm9tcHQudHJpbSgpfVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgID5cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAge2ltYWdlR2VuZXJhdGVMb2FkaW5nID8gPFNwaW5uZXIgLz4gOiAnR2VuZXJhdGUnfVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIDwvQnV0dG9uPlxuICAgICAgICAgICAgICAgICAgICAgICAgPC9kaXY+XG5cbiAgICAgICAgICAgICAgICAgICAgICAgIHtpbWFnZUVycm9yID8gKFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxTdGF0dXNNZXNzYWdlIHRvbmU9XCJlcnJvclwiIHRpdGxlPVwiSW1hZ2UgZ2VuZXJhdGlvbiBmYWlsZWRcIj5cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAge2ltYWdlRXJyb3J9XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgPC9TdGF0dXNNZXNzYWdlPlxuICAgICAgICAgICAgICAgICAgICAgICAgKSA6IG51bGx9XG5cbiAgICAgICAgICAgICAgICAgICAgICAgIHtpbWFnZU5vdGljZSA/IChcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICA8U3RhdHVzTWVzc2FnZSB0b25lPVwic3VjY2Vzc1wiIHRpdGxlPVwiSW1hZ2Ugd29ya2Zsb3dcIj5cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAge2ltYWdlTm90aWNlfVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIDwvU3RhdHVzTWVzc2FnZT5cbiAgICAgICAgICAgICAgICAgICAgICAgICkgOiBudWxsfVxuXG4gICAgICAgICAgICAgICAgICAgICAgICB7aW1hZ2VSZWNvbW1lbmRhdGlvbj8ucmF0aW9uYWxlID8gKFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxOb3RpY2Ugc3RhdHVzPVwiaW5mb1wiIGlzRGlzbWlzc2libGU9e2ZhbHNlfT5cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAge2ltYWdlUmVjb21tZW5kYXRpb24ucmF0aW9uYWxlfVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIDwvTm90aWNlPlxuICAgICAgICAgICAgICAgICAgICAgICAgKSA6IG51bGx9XG5cbiAgICAgICAgICAgICAgICAgICAgICAgIHtpbWFnZVJlc3VsdD8uYXR0YWNobWVudHM/LlswXT8udXJsID8gKFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxkaXYgY2xhc3NOYW1lPVwiZHVhbC1ncHQtaW1hZ2UtcHJldmlld1wiPlxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8aW1nIHNyYz17aW1hZ2VSZXN1bHQuYXR0YWNobWVudHNbMF0udXJsfSBhbHQ9e2ltYWdlQWx0VGV4dCB8fCAnR2VuZXJhdGVkIGltYWdlIHByZXZpZXcnfSAvPlxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8ZGl2IGNsYXNzTmFtZT1cImR1YWwtZ3B0LWJ1dHRvbi1yb3dcIj5cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxCdXR0b24gaXNTZWNvbmRhcnkgb25DbGljaz17aW5zZXJ0R2VuZXJhdGVkSW1hZ2V9PlxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIEluc2VydCBJbmxpbmVcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDwvQnV0dG9uPlxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8L2Rpdj5cbiAgICAgICAgICAgICAgICAgICAgICAgICAgICA8L2Rpdj5cbiAgICAgICAgICAgICAgICAgICAgICAgICkgOiBudWxsfVxuICAgICAgICAgICAgICAgICAgICA8Lz5cbiAgICAgICAgICAgICAgICApIDogbnVsbH1cbiAgICAgICAgICAgIDwvUGFuZWxCb2R5PlxuXG4gICAgICAgICAgICA8UGFuZWxCb2R5IHRpdGxlPVwiUmVzZWFyY2ggUGFuZVwiIGluaXRpYWxPcGVuPXtmYWxzZX0+XG4gICAgICAgICAgICAgICAgPFRleHRhcmVhQ29udHJvbFxuICAgICAgICAgICAgICAgICAgICBsYWJlbD1cIlJlc2VhcmNoIFByb21wdFwiXG4gICAgICAgICAgICAgICAgICAgIHZhbHVlPXtyZXNlYXJjaFByb21wdH1cbiAgICAgICAgICAgICAgICAgICAgb25DaGFuZ2U9eyh2YWx1ZSkgPT4ge1xuICAgICAgICAgICAgICAgICAgICAgICAgc2V0UmVzZWFyY2hQcm9tcHQodmFsdWUpO1xuICAgICAgICAgICAgICAgICAgICAgICAgaWYgKHJlc2VhcmNoRXJyb3IpIHNldFJlc2VhcmNoRXJyb3IoJycpO1xuICAgICAgICAgICAgICAgICAgICB9fVxuICAgICAgICAgICAgICAgICAgICBwbGFjZWhvbGRlcj1cIkVudGVyIHlvdXIgcmVzZWFyY2ggcXVlcnkuLi5cIlxuICAgICAgICAgICAgICAgIC8+XG4gICAgICAgICAgICAgICAgPEJ1dHRvblxuICAgICAgICAgICAgICAgICAgICBpc1ByaW1hcnlcbiAgICAgICAgICAgICAgICAgICAgb25DbGljaz17aGFuZGxlUmVzZWFyY2hTdWJtaXR9XG4gICAgICAgICAgICAgICAgICAgIGRpc2FibGVkPXtyZXNlYXJjaExvYWRpbmcgfHwgIXJlc2VhcmNoUHJvbXB0LnRyaW0oKX1cbiAgICAgICAgICAgICAgICA+XG4gICAgICAgICAgICAgICAgICAgIHtyZXNlYXJjaExvYWRpbmcgPyA8U3Bpbm5lciAvPiA6ICdSZXNlYXJjaCd9XG4gICAgICAgICAgICAgICAgPC9CdXR0b24+XG4gICAgICAgICAgICAgICAge3Jlc2VhcmNoRXJyb3IgPyA8U3RhdHVzTWVzc2FnZSB0b25lPVwiZXJyb3JcIiB0aXRsZT1cIlJlc2VhcmNoIGVycm9yXCI+e3Jlc2VhcmNoRXJyb3J9PC9TdGF0dXNNZXNzYWdlPiA6IG51bGx9XG4gICAgICAgICAgICAgICAge3Jlc2VhcmNoUmVzdWx0cyAmJiAhcmVzZWFyY2hFcnJvciA/IDxTdGF0dXNNZXNzYWdlIHRvbmU9XCJzdWNjZXNzXCIgdGl0bGU9XCJSZXNlYXJjaFwiPntyZXNlYXJjaFJlc3VsdHN9PC9TdGF0dXNNZXNzYWdlPiA6IG51bGx9XG4gICAgICAgICAgICA8L1BhbmVsQm9keT5cblxuICAgICAgICAgICAgPFBhbmVsQm9keSB0aXRsZT1cIkF1dGhvciBBZ2VudFwiIGluaXRpYWxPcGVuPXtmYWxzZX0+XG4gICAgICAgICAgICAgICAgPGxhYmVsIHN0eWxlPXt7IGRpc3BsYXk6ICdibG9jaycsIG1hcmdpbkJvdHRvbTogJzZweCcsIGZvbnRXZWlnaHQ6IDYwMCB9fT5Nb2RlPC9sYWJlbD5cbiAgICAgICAgICAgICAgICA8c2VsZWN0XG4gICAgICAgICAgICAgICAgICAgIHZhbHVlPXthdXRob3JNb2RlfVxuICAgICAgICAgICAgICAgICAgICBvbkNoYW5nZT17KGV2ZW50KSA9PiBzZXRBdXRob3JNb2RlKGV2ZW50LnRhcmdldC52YWx1ZSl9XG4gICAgICAgICAgICAgICAgICAgIHN0eWxlPXt7IHdpZHRoOiAnMTAwJScsIG1hcmdpbkJvdHRvbTogJzEycHgnIH19XG4gICAgICAgICAgICAgICAgPlxuICAgICAgICAgICAgICAgICAgICA8b3B0aW9uIHZhbHVlPVwiZHJhZnRcIj5EcmFmdDwvb3B0aW9uPlxuICAgICAgICAgICAgICAgICAgICA8b3B0aW9uIHZhbHVlPVwiYWJzdHJhY3RcIj5BYnN0cmFjdDwvb3B0aW9uPlxuICAgICAgICAgICAgICAgICAgICA8b3B0aW9uIHZhbHVlPVwiZW5yaWNobWVudFwiPkVucmljaG1lbnQ8L29wdGlvbj5cbiAgICAgICAgICAgICAgICA8L3NlbGVjdD5cblxuICAgICAgICAgICAgICAgIDxUZXh0YXJlYUNvbnRyb2xcbiAgICAgICAgICAgICAgICAgICAgbGFiZWw9XCJGcmFtZXdvcmsgQnJpZWYgSURcIlxuICAgICAgICAgICAgICAgICAgICB2YWx1ZT17ZnJhbWV3b3JrQnJpZWZJZH1cbiAgICAgICAgICAgICAgICAgICAgb25DaGFuZ2U9eyh2YWx1ZSkgPT4gc2V0RnJhbWV3b3JrQnJpZWZJZCh2YWx1ZSl9XG4gICAgICAgICAgICAgICAgICAgIHBsYWNlaG9sZGVyPVwiRkcgYnJpZWYgSUQgKHJlcXVpcmVkIGZvciBkcmFmdClcIlxuICAgICAgICAgICAgICAgIC8+XG4gICAgICAgICAgICAgICAgPFRleHRhcmVhQ29udHJvbFxuICAgICAgICAgICAgICAgICAgICBsYWJlbD1cIlBsYW5uZXIgU2Vzc2lvbiBJRFwiXG4gICAgICAgICAgICAgICAgICAgIHZhbHVlPXtwbGFubmVyU2Vzc2lvbklkfVxuICAgICAgICAgICAgICAgICAgICBvbkNoYW5nZT17KHZhbHVlKSA9PiBzZXRQbGFubmVyU2Vzc2lvbklkKHZhbHVlKX1cbiAgICAgICAgICAgICAgICAgICAgcGxhY2Vob2xkZXI9XCJFZGl0b3JpYWwgUGxhbm5lciBzZXNzaW9uIElEIChyZXF1aXJlZCBmb3IgZHJhZnQpXCJcbiAgICAgICAgICAgICAgICAvPlxuICAgICAgICAgICAgICAgIDxUZXh0YXJlYUNvbnRyb2xcbiAgICAgICAgICAgICAgICAgICAgbGFiZWw9XCJBdXRob3IgSW5zdHJ1Y3Rpb25zIChvcHRpb25hbClcIlxuICAgICAgICAgICAgICAgICAgICB2YWx1ZT17YXV0aG9ySW5zdHJ1Y3Rpb25zfVxuICAgICAgICAgICAgICAgICAgICBvbkNoYW5nZT17KHZhbHVlKSA9PiBzZXRBdXRob3JJbnN0cnVjdGlvbnModmFsdWUpfVxuICAgICAgICAgICAgICAgICAgICBwbGFjZWhvbGRlcj1cIk9wdGlvbmFsIGNvbnN0cmFpbnRzIG9yIG5vdGVzXCJcbiAgICAgICAgICAgICAgICAvPlxuXG4gICAgICAgICAgICAgICAgPFBhbmVsQm9keSB0aXRsZT1cIkNvcmUgU2V0dGluZ3NcIiBpbml0aWFsT3Blbj17ZmFsc2V9PlxuICAgICAgICAgICAgICAgICAgICA8VGV4dGFyZWFDb250cm9sXG4gICAgICAgICAgICAgICAgICAgICAgICBsYWJlbD1cIkluZHVzdHJ5IEZvY3VzXCJcbiAgICAgICAgICAgICAgICAgICAgICAgIHZhbHVlPXthdXRob3JDb3JlU2V0dGluZ3MuaW5kdXN0cnlfZm9jdXN9XG4gICAgICAgICAgICAgICAgICAgICAgICBvbkNoYW5nZT17KHZhbHVlKSA9PiBzZXRBdXRob3JDb3JlU2V0dGluZ3MoeyAuLi5hdXRob3JDb3JlU2V0dGluZ3MsIGluZHVzdHJ5X2ZvY3VzOiB2YWx1ZSB9KX1cbiAgICAgICAgICAgICAgICAgICAgLz5cbiAgICAgICAgICAgICAgICAgICAgPFRleHRhcmVhQ29udHJvbFxuICAgICAgICAgICAgICAgICAgICAgICAgbGFiZWw9XCJBdWRpZW5jZSBUaWVyXCJcbiAgICAgICAgICAgICAgICAgICAgICAgIHZhbHVlPXthdXRob3JDb3JlU2V0dGluZ3MuYXVkaWVuY2VfdGllcn1cbiAgICAgICAgICAgICAgICAgICAgICAgIG9uQ2hhbmdlPXsodmFsdWUpID0+IHNldEF1dGhvckNvcmVTZXR0aW5ncyh7IC4uLmF1dGhvckNvcmVTZXR0aW5ncywgYXVkaWVuY2VfdGllcjogdmFsdWUgfSl9XG4gICAgICAgICAgICAgICAgICAgIC8+XG4gICAgICAgICAgICAgICAgICAgIDxUZXh0YXJlYUNvbnRyb2xcbiAgICAgICAgICAgICAgICAgICAgICAgIGxhYmVsPVwiUmlzayBUb2xlcmFuY2VcIlxuICAgICAgICAgICAgICAgICAgICAgICAgdmFsdWU9e2F1dGhvckNvcmVTZXR0aW5ncy5yaXNrX3RvbGVyYW5jZX1cbiAgICAgICAgICAgICAgICAgICAgICAgIG9uQ2hhbmdlPXsodmFsdWUpID0+IHNldEF1dGhvckNvcmVTZXR0aW5ncyh7IC4uLmF1dGhvckNvcmVTZXR0aW5ncywgcmlza190b2xlcmFuY2U6IHZhbHVlIH0pfVxuICAgICAgICAgICAgICAgICAgICAvPlxuICAgICAgICAgICAgICAgICAgICA8VGV4dGFyZWFDb250cm9sXG4gICAgICAgICAgICAgICAgICAgICAgICBsYWJlbD1cIkJyYW5kIFByb2ZpbGVcIlxuICAgICAgICAgICAgICAgICAgICAgICAgdmFsdWU9e2F1dGhvckNvcmVTZXR0aW5ncy5icmFuZF9wcm9maWxlfVxuICAgICAgICAgICAgICAgICAgICAgICAgb25DaGFuZ2U9eyh2YWx1ZSkgPT4gc2V0QXV0aG9yQ29yZVNldHRpbmdzKHsgLi4uYXV0aG9yQ29yZVNldHRpbmdzLCBicmFuZF9wcm9maWxlOiB2YWx1ZSB9KX1cbiAgICAgICAgICAgICAgICAgICAgLz5cbiAgICAgICAgICAgICAgICA8L1BhbmVsQm9keT5cblxuICAgICAgICAgICAgICAgIDxkaXYgY2xhc3NOYW1lPVwiZHVhbC1ncHQtYnV0dG9uLXJvd1wiPlxuICAgICAgICAgICAgICAgICAgICA8QnV0dG9uXG4gICAgICAgICAgICAgICAgICAgICAgICBpc1ByaW1hcnlcbiAgICAgICAgICAgICAgICAgICAgICAgIG9uQ2xpY2s9e2hhbmRsZUF1dGhvclN1Ym1pdH1cbiAgICAgICAgICAgICAgICAgICAgICAgIGRpc2FibGVkPXthdXRob3JMb2FkaW5nfVxuICAgICAgICAgICAgICAgICAgICA+XG4gICAgICAgICAgICAgICAgICAgICAgICB7YXV0aG9yTG9hZGluZyA/IDxTcGlubmVyIC8+IDogJ1J1biBBdXRob3IgQWdlbnQnfVxuICAgICAgICAgICAgICAgICAgICA8L0J1dHRvbj5cbiAgICAgICAgICAgICAgICAgICAgPEJ1dHRvblxuICAgICAgICAgICAgICAgICAgICAgICAgaXNTZWNvbmRhcnlcbiAgICAgICAgICAgICAgICAgICAgICAgIG9uQ2xpY2s9e2luc2VydEJsb2Nrc0Zyb21BdXRob3J9XG4gICAgICAgICAgICAgICAgICAgICAgICBkaXNhYmxlZD17IWF1dGhvckJsb2Nrcy5sZW5ndGggfHwgISFhdXRob3JFcnJvcn1cbiAgICAgICAgICAgICAgICAgICAgPlxuICAgICAgICAgICAgICAgICAgICAgICAgSW5zZXJ0IEJsb2Nrc1xuICAgICAgICAgICAgICAgICAgICA8L0J1dHRvbj5cbiAgICAgICAgICAgICAgICA8L2Rpdj5cblxuICAgICAgICAgICAgICAgIHthdXRob3JFcnJvciA/IDxTdGF0dXNNZXNzYWdlIHRvbmU9XCJlcnJvclwiIHRpdGxlPVwiQXV0aG9yIGVycm9yXCI+e2F1dGhvckVycm9yfTwvU3RhdHVzTWVzc2FnZT4gOiBudWxsfVxuICAgICAgICAgICAgICAgIHthdXRob3JXYXJuaW5ncy5sZW5ndGggPiAwID8gKFxuICAgICAgICAgICAgICAgICAgICA8U3RhdHVzTWVzc2FnZSB0b25lPVwid2FybmluZ1wiIHRpdGxlPVwiV2FybmluZ3NcIj5cbiAgICAgICAgICAgICAgICAgICAgICAgIDx1bD57YXV0aG9yV2FybmluZ3MubWFwKCh3YXJuaW5nLCBpbmRleCkgPT4gPGxpIGtleT17aW5kZXh9Pnt3YXJuaW5nfTwvbGk+KX08L3VsPlxuICAgICAgICAgICAgICAgICAgICA8L1N0YXR1c01lc3NhZ2U+XG4gICAgICAgICAgICAgICAgKSA6IG51bGx9XG4gICAgICAgICAgICAgICAge2F1dGhvclZhbGlkYXRpb25FcnJvcnMubGVuZ3RoID4gMCA/IChcbiAgICAgICAgICAgICAgICAgICAgPFN0YXR1c01lc3NhZ2UgdG9uZT1cImVycm9yXCIgdGl0bGU9XCJWYWxpZGF0aW9uIEVycm9yc1wiPlxuICAgICAgICAgICAgICAgICAgICAgICAgPHVsPnthdXRob3JWYWxpZGF0aW9uRXJyb3JzLm1hcCgoZXJyb3IsIGluZGV4KSA9PiA8bGkga2V5PXtpbmRleH0+e2Vycm9yfTwvbGk+KX08L3VsPlxuICAgICAgICAgICAgICAgICAgICA8L1N0YXR1c01lc3NhZ2U+XG4gICAgICAgICAgICAgICAgKSA6IG51bGx9XG4gICAgICAgICAgICAgICAge2F1dGhvclJlc3VsdHMgJiYgIWF1dGhvckVycm9yID8gPFN0YXR1c01lc3NhZ2UgdG9uZT1cInN1Y2Nlc3NcIiB0aXRsZT1cIkF1dGhvclwiPnthdXRob3JSZXN1bHRzfTwvU3RhdHVzTWVzc2FnZT4gOiBudWxsfVxuICAgICAgICAgICAgICAgIHthdXRob3JBYnN0cmFjdCA/IChcbiAgICAgICAgICAgICAgICAgICAgPGRpdiBjbGFzc05hbWU9XCJkdWFsLWdwdC1yZXN1bHRzXCI+XG4gICAgICAgICAgICAgICAgICAgICAgICA8c3Ryb25nPkFic3RyYWN0IE91dHB1dDo8L3N0cm9uZz5cbiAgICAgICAgICAgICAgICAgICAgICAgIDxwcmUgc3R5bGU9e3sgd2hpdGVTcGFjZTogJ3ByZS13cmFwJyB9fT57SlNPTi5zdHJpbmdpZnkoYXV0aG9yQWJzdHJhY3QsIG51bGwsIDIpfTwvcHJlPlxuICAgICAgICAgICAgICAgICAgICA8L2Rpdj5cbiAgICAgICAgICAgICAgICApIDogbnVsbH1cbiAgICAgICAgICAgIDwvUGFuZWxCb2R5PlxuICAgICAgICA8L1BsdWdpblNpZGViYXI+XG4gICAgKTtcbn07XG5cbnJlZ2lzdGVyUGx1Z2luKCdkdWFsLWdwdC1zaWRlYmFyJywge1xuICAgIHJlbmRlcjogRHVhbEdQVFNpZGViYXIsXG4gICAgaWNvbjogJ2FkbWluLXRvb2xzJyxcbn0pO1xuIl0sIm1hcHBpbmdzIjoiOzs7Ozs7MEJBQ0EsdUtBQUFBLENBQUEsRUFBQUMsQ0FBQSxFQUFBQyxDQUFBLHdCQUFBQyxNQUFBLEdBQUFBLE1BQUEsT0FBQUMsQ0FBQSxHQUFBRixDQUFBLENBQUFHLFFBQUEsa0JBQUFDLENBQUEsR0FBQUosQ0FBQSxDQUFBSyxXQUFBLDhCQUFBQyxFQUFBTixDQUFBLEVBQUFFLENBQUEsRUFBQUUsQ0FBQSxFQUFBRSxDQUFBLFFBQUFDLENBQUEsR0FBQUwsQ0FBQSxJQUFBQSxDQUFBLENBQUFNLFNBQUEsWUFBQUMsU0FBQSxHQUFBUCxDQUFBLEdBQUFPLFNBQUEsRUFBQUMsQ0FBQSxHQUFBQyxNQUFBLENBQUFDLE1BQUEsQ0FBQUwsQ0FBQSxDQUFBQyxTQUFBLFVBQUFLLG1CQUFBLENBQUFILENBQUEsdUJBQUFWLENBQUEsRUFBQUUsQ0FBQSxFQUFBRSxDQUFBLFFBQUFFLENBQUEsRUFBQUMsQ0FBQSxFQUFBRyxDQUFBLEVBQUFJLENBQUEsTUFBQUMsQ0FBQSxHQUFBWCxDQUFBLFFBQUFZLENBQUEsT0FBQUMsQ0FBQSxLQUFBRixDQUFBLEtBQUFiLENBQUEsS0FBQWdCLENBQUEsRUFBQXBCLENBQUEsRUFBQXFCLENBQUEsRUFBQUMsQ0FBQSxFQUFBTixDQUFBLEVBQUFNLENBQUEsQ0FBQUMsSUFBQSxDQUFBdkIsQ0FBQSxNQUFBc0IsQ0FB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QUFDRyxJQUFJLEVBQUMsU0FBUztJQUFDQyxLQUFLLEVBQUM7RUFBVSxHQUMxQzVCLEVBQUEsQ0FBQWUsT0FBQSxDQUFBZSxhQUFBLGFBQUtpRixjQUFjLENBQUNnTSxHQUFHLENBQUMsVUFBQzJGLE9BQU8sRUFBRUMsS0FBSztJQUFBLE9BQUszWSxFQUFBLENBQUFlLE9BQUEsQ0FBQWUsYUFBQTtNQUFJOFcsR0FBRyxFQUFFRDtJQUFNLEdBQUVELE9BQVksQ0FBQztFQUFBLEVBQU0sQ0FDckUsQ0FBQyxHQUNoQixJQUFJLEVBQ1B2UixzQkFBc0IsQ0FBQ25LLE1BQU0sR0FBRyxDQUFDLEdBQzlCZ0QsRUFBQSxDQUFBZSxPQUFBLENBQUFlLGFBQUEsQ0FBQ04sYUFBYTtJQUFDRyxJQUFJLEVBQUMsT0FBTztJQUFDQyxLQUFLLEVBQUM7RUFBbUIsR0FDakQ1QixFQUFBLENBQUFlLE9BQUEsQ0FBQWUsYUFBQSxhQUFLcUYsc0JBQXNCLENBQUM0TCxHQUFHLENBQUMsVUFBQzhGLEtBQUssRUFBRUYsS0FBSztJQUFBLE9BQUszWSxFQUFBLENBQUFlLE9BQUEsQ0FBQWUsYUFBQTtNQUFJOFcsR0FBRyxFQUFFRDtJQUFNLEdBQUVFLEtBQVUsQ0FBQztFQUFBLEVBQU0sQ0FDekUsQ0FBQyxHQUNoQixJQUFJLEVBQ1AxVCxhQUFhLElBQUksQ0FBQ1EsV0FBVyxHQUFHM0YsRUFBQSxDQUFBZSxPQUFBLENBQUFlLGFBQUEsQ0FBQ04sYUFBYTtJQUFDRyxJQUFJLEVBQUMsU0FBUztJQUFDQyxLQUFLLEVBQUM7RUFBUSxHQUFFdUQsYUFBNkIsQ0FBQyxHQUFHLElBQUksRUFDbkh3QixjQUFjLEdBQ1gzRyxFQUFBLENBQUFlLE9BQUEsQ0FBQWUsYUFBQTtJQUFLQyxTQUFTLEVBQUM7RUFBa0IsR0FDN0IvQixFQUFBLENBQUFlLE9BQUEsQ0FBQWUsYUFBQSxpQkFBUSxrQkFBd0IsQ0FBQyxFQUNqQzlCLEVBQUEsQ0FBQWUsT0FBQSxDQUFBZSxhQUFBO0lBQUtvVyxLQUFLLEVBQUU7TUFBRVksVUFBVSxFQUFFO0lBQVc7RUFBRSxHQUFFQyxJQUFJLENBQUNDLFNBQVMsQ0FBQ3JTLGNBQWMsRUFBRSxJQUFJLEVBQUUsQ0FBQyxDQUFPLENBQ3JGLENBQUMsR0FDTixJQUNHLENBQ0EsQ0FBQztBQUV4QixDQUFDO0FBRUQ1RyxjQUFjLENBQUMsa0JBQWtCLEVBQUU7RUFDL0JrWixNQUFNLEVBQUVoWCxjQUFjO0VBQ3RCaVUsSUFBSSxFQUFFO0FBQ1YsQ0FBQyxDQUFDIiwiaWdub3JlTGlzdCI6W119