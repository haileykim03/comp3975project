import React from 'react';
import { Link } from 'react-router-dom';
import 'bootstrap/dist/css/bootstrap.min.css';
import '../App.css';
import Header from '../components/Header';
import Footer from '../components/Footer';

const NotFoundPage: React.FC = () => {
  return (
    <>
      <Header />

      <div className="auth-page d-flex justify-content-center align-items-center min-vh-100">
        <div className="auth-card text-center">
          <img
            src="/images/bcit-logo.png"
            alt="BCIT Logo"
            className="brand-logo mb-3"
          />

          <h1 className="auth-title">404</h1>
          <p className="auth-subtitle">Page Not Found</p>

          <div className="mt-4">
            <p className="text-muted mb-4">
              The page you're looking for doesn't exist on BCIT Lost & Found.
            </p>
            <Link
              to="/"
              className="btn btn-primary"
            >
              Go Home
            </Link>
          </div>
        </div>
      </div>

      <Footer />
    </>
  );
};

export default NotFoundPage;