import {
  BrowserRouter,
  Navigate,
  Route,
  Routes,
  useLocation,
} from "react-router-dom";
import { Providers } from "./providers";
import { useAuth } from "../features/auth/AuthProvider";
import { LoginPage } from "../features/auth/LoginPage";
import { AdminLayout } from "../components/layout/AdminLayout";
import { DashboardPage } from "../features/dashboard/DashboardPage";
import { UsersPage, UserDetailPage } from "../features/users/UsersPages";
import {
  ArticlesPage,
  ArticleEditorPage,
  ExercisesPage,
  ExerciseEditorPage,
} from "../features/content/ContentPages";
import {
  SpacesPage,
  AudioEditorPage,
  SpacesArchivePage,
  SpacesLinksPage,
} from "../features/spaces/SpacesPages";
import {
  CounselorsPage,
  CounselorEditorPage,
  HotlinesPage,
  HotlineEditorPage,
} from "../features/sos/SosPages";
import {
  CommentsPage,
  CommentDetailPage,
  FeedbackPage,
  FeedbackDetailPage,
} from "../features/moderation/ModerationPages";
import {
  RecoveryPage,
  VerifyPage,
  SecurityPage,
  StatesPage,
} from "../features/account/AccountPages";
function Guard() {
  const { user, loading } = useAuth();
  const loc = useLocation();
  if (loading)
    return (
      <div className="boot">
        <span className="spinner" />
        جارٍ التحقق من الجلسة…
      </div>
    );
  return user ? (
    <AdminLayout />
  ) : (
    <Navigate to="/login" replace state={{ from: loc.pathname }} />
  );
}
export function App() {
  return (
    <Providers>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/recovery" element={<RecoveryPage />} />
          <Route path="/verify" element={<VerifyPage />} />
          <Route element={<Guard />}>
            <Route index element={<DashboardPage />} />
            <Route path="users" element={<UsersPage />} />
            <Route path="users/:id" element={<UserDetailPage />} />
            <Route path="articles" element={<ArticlesPage />} />
            <Route path="articles/new" element={<ArticleEditorPage />} />
            <Route path="articles/:id/edit" element={<ArticleEditorPage />} />
            <Route path="exercises" element={<ExercisesPage />} />
            <Route path="exercises/new" element={<ExerciseEditorPage />} />
            <Route path="exercises/:id/edit" element={<ExerciseEditorPage />} />
            <Route path="spaces" element={<SpacesPage />} />
            <Route path="spaces/new" element={<AudioEditorPage />} />
            <Route path="spaces/:id/edit" element={<AudioEditorPage />} />
            <Route path="spaces/archive" element={<SpacesArchivePage />} />
            <Route path="spaces/links" element={<SpacesLinksPage />} />
            <Route path="sos/counselors" element={<CounselorsPage />} />
            <Route
              path="sos/counselors/new"
              element={<CounselorEditorPage />}
            />
            <Route
              path="sos/counselors/:id/edit"
              element={<CounselorEditorPage />}
            />
            <Route path="sos/hotlines" element={<HotlinesPage />} />
            <Route path="sos/hotlines/new" element={<HotlineEditorPage />} />
            <Route
              path="sos/hotlines/:id/edit"
              element={<HotlineEditorPage />}
            />
            <Route path="comments" element={<CommentsPage />} />
            <Route path="comments/:id" element={<CommentDetailPage />} />
            <Route path="feedback" element={<FeedbackPage />} />
            <Route path="feedback/:id" element={<FeedbackDetailPage />} />
            <Route path="security" element={<SecurityPage />} />
            <Route path="states" element={<StatesPage />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </Providers>
  );
}
